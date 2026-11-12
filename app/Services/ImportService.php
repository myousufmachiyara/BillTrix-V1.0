<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Party;
use App\Models\Product;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Bulk import of products and customers/suppliers from a spreadsheet.
 * Every row is validated first; nothing is saved unless the whole file is clean.
 * Existing records are matched (products by SKU, parties by code) and updated.
 */
class ImportService
{
    public const TEMPLATES = [
        'products' => [
            'headings' => ['Name*', 'SKU', 'Barcode', 'Category', 'Unit* (pcs, kg …)', 'Purchase price', 'Sale price', 'Reorder level', 'HS code', 'Tax rate %', 'Opening qty', 'Opening cost'],
            'sample' => ['Cooking Oil 1L Pouch', 'OIL-001', '8964000100011', 'Grocery', 'pcs', 520, 585, 24, '1507.9000', 18, 50, 520],
        ],
        'parties' => [
            'headings' => ['Type* (customer/supplier/both)', 'Name*', 'Code', 'Phone', 'Email', 'Address', 'City', 'NTN', 'CNIC', 'Credit limit', 'Credit days', 'Opening balance (+ owes us, − we owe)'],
            'sample' => ['customer', 'Madina General Store', 'C-1001', '0300-1234567', '', 'Main Bazaar', 'Karachi', '', '42101-1234567-1', 100000, 15, 18500],
        ],
    ];

    public function __construct(private StockDocumentService $docs, private SequenceService $sequences) {}

    /** @return array{created:int, updated:int, errors:array<int, string[]>} */
    public function products(array $rows, bool $dryRun = false): array
    {
        $units = Unit::all();
        $unitFor = fn ($v) => $units->first(fn ($u) => strcasecmp($u->short_name, (string) $v) === 0 || strcasecmp($u->name, (string) $v) === 0);
        $taxes = TaxRate::all();
        $errors = [];
        $skus = [];

        foreach ($rows as $line => $r) {
            $v = Validator::make($r, [
                'name' => ['required', 'string', 'max:150'],
                'sku' => ['nullable', 'max:50'],
                'barcode' => ['nullable', 'max:64'],
                'unit' => ['required'],
                'purchase_price' => ['nullable', 'numeric', 'min:0'],
                'sale_price' => ['nullable', 'numeric', 'min:0'],
                'reorder_level' => ['nullable', 'numeric', 'min:0'],
                'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
                'opening_qty' => ['nullable', 'numeric', 'min:0'],
                'opening_cost' => ['nullable', 'numeric', 'min:0'],
            ]);
            $msgs = $v->errors()->all();
            if (! empty($r['unit']) && ! $unitFor($r['unit'])) {
                $msgs[] = "Unit \"{$r['unit']}\" does not exist. Add it under Inventory → Units first.";
            }
            if (! empty($r['tax_rate']) && ! $taxes->first(fn ($t) => abs((float) $t->rate - (float) $r['tax_rate']) < 0.0001)) {
                $msgs[] = "No tax rate of {$r['tax_rate']}% is set up.";
            }
            $sku = trim((string) ($r['sku'] ?? ''));
            if ($sku !== '' && isset($skus[$sku])) {
                $msgs[] = "SKU {$sku} appears twice in the file (row {$skus[$sku]}).";
            }
            $skus[$sku ?: '#'.$line] = $line;
            if ($msgs) {
                $errors[$line] = $msgs;
            }
        }

        if ($errors || $dryRun) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }

        return DB::transaction(function () use ($rows, $unitFor, $taxes) {
            $created = $updated = 0;
            $categories = Category::pluck('id', 'name')->mapWithKeys(fn ($id, $n) => [mb_strtolower($n) => $id]);
            $warehouse = Warehouse::where('is_default', true)->value('id') ?? Warehouse::value('id');
            $opening = [];

            foreach ($rows as $r) {
                $catId = null;
                if (! empty($r['category'])) {
                    $key = mb_strtolower($r['category']);
                    $catId = $categories[$key] ??= Category::create(['name' => $r['category']])->id;
                }
                $values = array_filter([
                    'name' => $r['name'],
                    'barcode' => $r['barcode'] ?? null ?: null,
                    'category_id' => $catId,
                    'unit_id' => $unitFor($r['unit'])->id,
                    'purchase_price' => $r['purchase_price'] ?? null,
                    'sale_price' => $r['sale_price'] ?? null,
                    'reorder_level' => $r['reorder_level'] ?? null,
                    // Excel turns 3401.1100 into the number 3401.11; HS codes always have four decimals.
                    'hs_code' => isset($r['hs_code']) && $r['hs_code'] !== '' ? (is_numeric($r['hs_code']) && ! is_string($r['hs_code']) ? sprintf('%.4f', $r['hs_code']) : (string) $r['hs_code']) : null,
                    'tax_rate_id' => ! empty($r['tax_rate']) ? $taxes->first(fn ($t) => abs((float) $t->rate - (float) $r['tax_rate']) < 0.0001)->id : null,
                ], fn ($v) => $v !== null && $v !== '');

                $sku = trim((string) ($r['sku'] ?? ''));
                $product = $sku !== '' ? Product::where('sku', $sku)->first() : null;
                if ($product) {
                    if ($product->movements()->exists()) {
                        unset($values['unit_id']); // base unit is fixed once stock has moved
                    }
                    $product->update($values);
                    $updated++;
                } else {
                    $product = Product::create($values + ['sku' => $sku ?: null]);
                    $created++;
                    if ((float) ($r['opening_qty'] ?? 0) > 0 && $product->track_stock) {
                        $opening[] = ['product_id' => $product->id, 'quantity' => (float) $r['opening_qty'],
                            'unit_cost' => (float) ($r['opening_cost'] ?? $r['purchase_price'] ?? 0)];
                    }
                }
            }

            if ($opening && $warehouse) {
                $this->docs->adjust(['warehouse_id' => $warehouse, 'date' => now()->toDateString(), 'type' => 'opening',
                    'notes' => 'Opening stock from spreadsheet import', 'items' => $opening]);
            }

            return ['created' => $created, 'updated' => $updated, 'errors' => []];
        });
    }

    /** @return array{created:int, updated:int, errors:array<int, string[]>} */
    public function parties(array $rows, bool $dryRun = false): array
    {
        $errors = [];
        foreach ($rows as $line => $r) {
            $r['type'] = strtolower((string) ($r['type'] ?? ''));
            $v = Validator::make($r, [
                'type' => ['required', 'in:customer,supplier,both'],
                'name' => ['required', 'string', 'max:120'],
                'email' => ['nullable', 'email'],
                'credit_limit' => ['nullable', 'numeric', 'min:0'],
                'credit_days' => ['nullable', 'integer', 'min:0'],
                'opening_balance' => ['nullable', 'numeric'],
            ], ['type.in' => 'Type must be customer, supplier or both.']);
            if ($v->fails()) {
                $errors[$line] = $v->errors()->all();
            }
        }

        if ($errors || $dryRun) {
            return ['created' => 0, 'updated' => 0, 'errors' => $errors];
        }

        return DB::transaction(function () use ($rows) {
            $created = $updated = 0;
            foreach ($rows as $r) {
                $type = strtolower($r['type']);
                $values = [
                    'type' => $type, 'name' => $r['name'],
                    'phone' => $r['phone'] ?? null, 'email' => $r['email'] ?? null, 'address' => $r['address'] ?? null, 'city' => $r['city'] ?? null,
                    'ntn' => isset($r['ntn']) ? (string) $r['ntn'] : null, 'cnic' => isset($r['cnic']) ? (string) $r['cnic'] : null,
                    'credit_limit' => (float) ($r['credit_limit'] ?? 0), 'credit_days' => (int) ($r['credit_days'] ?? 0),
                ];
                $code = trim((string) ($r['code'] ?? ''));
                $party = $code !== '' ? Party::where('code', $code)->first() : null;
                if ($party) {
                    $party->update($values);
                    $updated++;
                } else {
                    $party = Party::create($values + ['code' => $code ?: $this->sequences->next($type === 'supplier' ? 'supplier' : 'customer')]);
                    $created++;
                }
                if (isset($r['opening_balance']) && $r['opening_balance'] !== '' && $r['opening_balance'] !== null) {
                    $this->docs->partyOpening($party, (float) $r['opening_balance'], now()->toDateString());
                }
            }

            return ['created' => $created, 'updated' => $updated, 'errors' => []];
        });
    }
}
