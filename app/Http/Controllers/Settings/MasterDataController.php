<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PriceLevel;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** One screen for the small lookup lists: units, categories, price levels and tax rates. */
class MasterDataController extends Controller
{
    private const MASTERS = [
        'units' => [
            'model' => Unit::class, 'title' => 'Units of Measure', 'permission' => 'products.edit', 'order' => 'name',
            'fields' => [
                'name' => ['Name', 'text', ['required', 'string', 'max:40']],
                'short_name' => ['Short name', 'text', ['required', 'string', 'max:12']],
                'allow_decimal' => ['Allow decimals (kg, litre)', 'checkbox', ['boolean']],
            ],
        ],
        'categories' => [
            'model' => Category::class, 'title' => 'Product Categories', 'permission' => 'products.edit', 'order' => 'name',
            'fields' => [
                'name' => ['Name', 'text', ['required', 'string', 'max:80']],
                'parent_id' => ['Parent category', 'parent', ['nullable']],
            ],
        ],
        'price-levels' => [
            'model' => PriceLevel::class, 'title' => 'Price Levels', 'permission' => 'settings.manage', 'order' => 'id',
            'fields' => [
                'name' => ['Name', 'text', ['required', 'string', 'max:50']],
                'is_default' => ['Default for new customers', 'checkbox', ['boolean']],
            ],
        ],
        'tax-rates' => [
            'model' => TaxRate::class, 'title' => 'Tax Rates', 'permission' => 'settings.manage', 'order' => 'rate',
            'fields' => [
                'name' => ['Name', 'text', ['required', 'string', 'max:50']],
                'rate' => ['Rate %', 'number', ['required', 'numeric', 'min:0', 'max:100']],
                'is_active' => ['Active', 'checkbox', ['boolean']],
            ],
        ],
    ];

    public function index(string $master): View
    {
        $def = $this->def($master);
        $this->allow($def['permission']);

        $rows = $def['model']::query()->when($master === 'categories', fn ($q) => $q->with('parent'))->orderBy($def['order'])->get();

        return view('settings.masters', [
            'master' => $master,
            'def' => $def,
            'rows' => $rows,
            'parents' => $master === 'categories' ? Category::whereNull('parent_id')->orderBy('name')->pluck('name', 'id') : collect(),
        ]);
    }

    public function store(Request $request, string $master): RedirectResponse
    {
        $def = $this->def($master);
        $this->allow($def['permission']);
        $this->persist(new $def['model'], $request, $def);

        return back()->with('success', 'Saved.');
    }

    public function update(Request $request, string $master, int $id): RedirectResponse
    {
        $def = $this->def($master);
        $this->allow($def['permission']);
        $this->persist($def['model']::findOrFail($id), $request, $def);

        return back()->with('success', 'Updated.');
    }

    public function destroy(string $master, int $id): RedirectResponse
    {
        $def = $this->def($master);
        $this->allow($def['permission']);

        try {
            $def['model']::findOrFail($id)->delete();
        } catch (QueryException) {
            return back()->with('error', 'This record is in use and cannot be deleted.');
        }

        return back()->with('success', 'Deleted.');
    }

    private function persist(Model $model, Request $request, array $def): void
    {
        $rules = [];
        foreach ($def['fields'] as $field => [, $type, $r]) {
            $rules[$field] = $type === 'parent' ? [...$r, $this->owned('categories')] : $r;
        }
        $data = $request->validate($rules);
        foreach ($def['fields'] as $field => [, $type]) {
            if ($type === 'checkbox') {
                $data[$field] = $request->boolean($field);
            }
        }
        if ($model instanceof Category && $model->exists && (int) ($data['parent_id'] ?? 0) === $model->id) {
            $data['parent_id'] = null;
        }
        if (($data['is_default'] ?? false) && $model instanceof PriceLevel) {
            PriceLevel::query()->update(['is_default' => false]);
        }
        $model->fill($data)->save();
    }

    private function def(string $master): array
    {
        return self::MASTERS[$master] ?? abort(404);
    }
}
