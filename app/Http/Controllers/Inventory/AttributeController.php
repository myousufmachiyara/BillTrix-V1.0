<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\ProductAttribute;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Variation attributes (Size, Colour, …) and their values. */
class AttributeController extends Controller
{
    public function index(): View
    {
        $this->allow('products.view');

        return view('inventory.attributes.index', [
            'attributes' => ProductAttribute::with(['values' => fn ($q) => $q->withCount('variants')])->orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('products.edit');
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $attribute = ProductAttribute::create(['name' => $data['name'], 'sort' => (int) ($data['sort'] ?? 0)]);
            $this->syncValues($attribute, $data['values']);
        });

        return $this->done(__(':n saved.', ['n' => $data['name']]), 'attributes.index');
    }

    public function update(Request $request, ProductAttribute $attribute): RedirectResponse
    {
        $this->allow('products.edit');
        $data = $this->validated($request, $attribute);
        DB::transaction(function () use ($attribute, $data) {
            $attribute->update(['name' => $data['name'], 'sort' => (int) ($data['sort'] ?? 0)]);
            $this->syncValues($attribute, $data['values']);
        });

        return $this->done(__(':n updated.', ['n' => $attribute->name]), 'attributes.index');
    }

    public function destroy(ProductAttribute $attribute): RedirectResponse
    {
        $this->allow('products.edit');
        if ($attribute->values()->whereHas('variants')->exists()) {
            return back()->with('error', __('This attribute is used by product variations and cannot be deleted.'));
        }
        $attribute->delete();

        return $this->done(__(':n deleted.', ['n' => $attribute->name]), 'attributes.index');
    }

    private function validated(Request $request, ?ProductAttribute $attribute = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('attributes')->where('business_id', Tenant::idOrFail())->ignore($attribute)],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'values' => ['required', 'string', 'max:2000'],
        ]);
        // "S, M, L" or one per line; "Red=RD" sets a short SKU code.
        $data['values'] = collect(preg_split('/[,\n]+/', $data['values']))->map(fn ($v) => trim($v))->filter()->unique(fn ($v) => mb_strtolower(trim(explode('=', $v)[0])))->values()->all();
        if (! $data['values']) {
            throw ValidationException::withMessages(['values' => __('Enter at least one value.')]);
        }

        return $data;
    }

    /** Keep values that are still listed (in the new order), add new ones, remove unused ones that were dropped. */
    private function syncValues(ProductAttribute $attribute, array $values): void
    {
        $existing = $attribute->values()->get()->keyBy(fn ($v) => mb_strtolower($v->value));
        $keep = [];
        foreach ($values as $i => $raw) {
            [$value, $code] = array_pad(array_map('trim', explode('=', $raw, 2)), 2, null);
            $value = mb_substr($value, 0, 60);
            $row = $existing->get(mb_strtolower($value)) ?? $attribute->values()->make();
            $row->fill(['value' => $value, 'code' => $code ? mb_substr($code, 0, 12) : $row->code, 'sort' => $i])->save();
            $keep[] = $row->id;
        }
        foreach ($existing->filter(fn ($v) => ! in_array($v->id, $keep, true)) as $old) {
            if ($old->variants()->exists()) {
                throw ValidationException::withMessages(['values' => __('":v" is used by product variations and cannot be removed.', ['v' => $old->value])]);
            }
            $old->delete();
        }
    }
}
