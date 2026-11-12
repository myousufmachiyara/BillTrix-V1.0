<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Party;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BusinessProvisioner;
use App\Services\CashSessionService;
use App\Services\DeliveryService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\StockDocumentService;
use App\Services\VoucherService;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/** A wholesaler with products, parties and a month of transactions — for demos. Login: demo@billtrix.pk / password */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::withoutGlobalScopes()->where('email', 'demo@billtrix.pk')->exists()) {
            return;
        }

        $owner = app(BusinessProvisioner::class)->create(
            ['name' => 'Al-Noor Traders', 'category' => 'wholesale', 'phone' => '0300-1234567', 'address' => 'Shop 12, Jodia Bazaar, Karachi'],
            ['name' => 'Demo Owner', 'email' => 'demo@billtrix.pk', 'password' => 'password'],
        );

        $owner->business->update(['settings' => ['pos' => true, 'pos_shifts' => true]]); // wholesaler with a counter

        Tenant::run($owner->business_id, function () use ($owner) {
            auth()->setUser($owner);
            $wh = Warehouse::first();
            $pcs = Unit::where('short_name', 'pcs')->value('id');
            $ctn = Unit::where('short_name', 'ctn')->value('id');
            $kg = Unit::where('short_name', 'kg')->value('id');
            $bag = Unit::where('short_name', 'bag')->value('id');
            $wholesale = PriceLevel::where('name', 'Wholesale')->value('id');

            $grocery = Category::create(['name' => 'Grocery']);
            $care = Category::create(['name' => 'Personal Care']);
            $bev = Category::create(['name' => 'Beverages']);

            $items = [
                ['Cooking Oil 1L Pouch', $grocery, $pcs, 520, 585, 12, '8964000100011'],
                ['Basmati Rice', $grocery, $kg, 290, 340, 25, '8964000100028'],
                ['Sugar', $grocery, $kg, 140, 155, 50, '8964000100035'],
                ['Tea 950g Pack', $bev, $pcs, 1450, 1620, 12, '8964000100042'],
                ['Mineral Water 1.5L', $bev, $pcs, 70, 90, 6, '8964000100059'],
                ['Bath Soap 115g', $care, $pcs, 95, 120, 72, '8964000100066'],
                ['Shampoo 360ml', $care, $pcs, 560, 650, 24, '8964000100073'],
                ['Washing Powder 1kg', $grocery, $pcs, 380, 440, 20, '8964000100080'],
            ];
            $products = [];
            foreach ($items as $i => [$name, $cat, $unit, $cost, $price, $pack, $barcode]) {
                $p = Product::create(['name' => $name, 'category_id' => $cat->id, 'unit_id' => $unit, 'sku' => 'ITM-'.str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                    'barcode' => $barcode, 'purchase_price' => $cost, 'sale_price' => $price, 'reorder_level' => $pack * 2]);
                ProductUnit::create(['product_id' => $p->id, 'unit_id' => $unit === $kg ? $bag : $ctn, 'factor' => $pack]);
                $p->prices()->create(['price_level_id' => $wholesale, 'price' => round($price * 0.95)]);
                $products[] = $p;
            }

            $suppliers = collect(['Karachi Distributors', 'Unity Foods Pvt Ltd', 'Hilal Sales Agency'])
                ->map(fn ($n) => Party::create(['type' => 'supplier', 'name' => $n, 'code' => 'S-'.substr(md5($n), 0, 4), 'phone' => '021-'.rand(3000000, 3999999), 'city' => 'Karachi']));
            $customers = collect(['Madina General Store', 'Bismillah Kiryana', 'City Mart', 'Rehman Super Store', 'Faisal Traders'])
                ->map(fn ($n, $i) => Party::create(['type' => 'customer', 'name' => $n, 'code' => 'C-'.($i + 101), 'phone' => '0300-'.rand(1000000, 9999999),
                    'city' => ['Karachi', 'Hyderabad', 'Karachi', 'Sukkur', 'Karachi'][$i], 'credit_limit' => 250000, 'credit_days' => 15, 'price_level_id' => $wholesale]));

            $docs = app(StockDocumentService::class);
            $docs->partyOpening($customers[0], 18500, now()->subDays(35)->toDateString());
            $docs->partyOpening($suppliers[0], -42000, now()->subDays(35)->toDateString());

            $invoices = app(InvoiceService::class);
            $start = now()->subDays(30);

            $invoices->save('purchase', [
                'date' => $start->copy()->subDay()->toDateString(), 'party_id' => $suppliers[1]->id, 'warehouse_id' => $wh->id, 'reference' => 'BILL-1001',
                'items' => collect($products)->map(fn ($p) => ['product_id' => $p->id, 'unit_id' => $p->units()->value('unit_id'),
                    'quantity' => 10, 'price' => $p->purchase_price * $p->units()->value('factor')])->all(),
            ]);
            foreach ($suppliers as $s) {
                $invoices->save('purchase', [
                    'date' => $start->toDateString(), 'party_id' => $s->id, 'warehouse_id' => $wh->id, 'reference' => 'BILL-'.rand(1000, 9999),
                    'items' => collect($products)->random(4)->map(fn ($p) => ['product_id' => $p->id, 'unit_id' => $p->units()->value('unit_id'),
                        'quantity' => rand(5, 15), 'price' => $p->purchase_price * $p->units()->value('factor')])->values()->all(),
                ]);
            }

            $walkIn = Party::where('is_walk_in', true)->first();
            for ($d = 28; $d >= 0; $d--) {
                $date = now()->subDays($d)->toDateString();
                foreach (range(1, rand(1, 3)) as $_) {
                    $retail = rand(0, 2) === 0;
                    $party = $retail ? $walkIn : $customers->random();
                    $lines = collect($products)->random(rand(1, 3))->map(fn ($p) => [
                        'product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => rand(1, $retail ? 3 : 10),
                        'price' => $retail ? $p->sale_price : $p->priceFor($wholesale),
                    ])->values()->all();
                    try {
                        $inv = $invoices->save('sale', ['date' => $date, 'party_id' => $party->id, 'warehouse_id' => $wh->id, 'items' => $lines,
                            'paid_amount' => $retail ? 999999 : 0, 'payment_mode' => $retail ? 'cash' : 'credit']);
                    } catch (\Illuminate\Validation\ValidationException) {
                        continue; // out of stock / credit limit in random data
                    }
                }
            }

            $vouchers = app(VoucherService::class);
            foreach ($customers->take(3) as $c) {
                $bal = $c->balance();
                if ($bal > 1000) {
                    $vouchers->save('receipt', ['date' => now()->subDays(3)->toDateString(), 'cash_account_id' => Account::system('cash')->id,
                        'party_id' => $c->id, 'amount' => round($bal * 0.6), 'narration' => 'Part payment received']);
                }
            }
            $vouchers->save('payment', ['date' => now()->subDays(2)->toDateString(), 'cash_account_id' => Account::system('cash')->id,
                'counter_account_id' => Account::where('name', 'Rent')->value('id'), 'amount' => 45000, 'narration' => 'Shop rent']);
            $vouchers->save('payment', ['date' => now()->subDays(2)->toDateString(), 'cash_account_id' => Account::system('bank')->id,
                'party_id' => $suppliers[0]->id, 'amount' => 30000, 'narration' => 'Cheque to supplier', 'reference' => 'CHQ-004512']);
            // Phase 2: quotation → sales order → partial challan; purchase order; special price; a closed register.
            $orders = app(OrderService::class);
            $line = fn ($p, $q, $price) => ['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => $q, 'price' => $price];
            $qt = $orders->save('quotation', ['date' => now()->subDays(6)->toDateString(), 'due_date' => now()->addDay()->toDateString(), 'party_id' => $customers[2]->id,
                'warehouse_id' => $wh->id, 'items' => [$line($products[5], 48, 114), $line($products[6], 12, 620)], 'terms' => 'Prices valid for 7 days.']);
            $so = $orders->save('sale_order', ['date' => now()->subDays(5)->toDateString(), 'party_id' => $customers[2]->id, 'warehouse_id' => $wh->id,
                'quotation_id' => $qt->id, 'reference' => 'CM-PO-221', 'items' => [$line($products[5], 48, 114), $line($products[6], 12, 620)]]);
            $orders->save('quotation', ['date' => now()->subDays(1)->toDateString(), 'due_date' => now()->addDays(6)->toDateString(), 'party_id' => $customers[4]->id,
                'warehouse_id' => $wh->id, 'items' => [$line($products[3], 10, 1580)]]);
            try {
                app(DeliveryService::class)->save(['date' => now()->subDays(4)->toDateString(), 'party_id' => $customers[2]->id, 'warehouse_id' => $wh->id,
                    'order_id' => $so->id, 'vehicle_no' => 'KHI-4521', 'driver' => 'Aslam',
                    'items' => [['product_id' => $products[5]->id, 'unit_id' => $products[5]->unit_id, 'quantity' => 24, 'order_item_id' => $so->items[0]->id]]]);
            } catch (\Illuminate\Validation\ValidationException) {
                // not enough random stock — skip
            }
            $orders->save('purchase_order', ['date' => now()->subDays(2)->toDateString(), 'due_date' => now()->addDays(5)->toDateString(), 'party_id' => $suppliers[1]->id,
                'warehouse_id' => $wh->id, 'items' => [$line($products[2], 500, 138), $line($products[0], 120, 515)]]);
            \App\Models\PartyPrice::create(['party_id' => $customers[0]->id, 'product_id' => $products[0]->id, 'price' => 560]);

            $registers = app(CashSessionService::class);
            $reg = $registers->open($owner, Account::system('cash')->id, 5000);
            foreach ([[$products[4], 6], [$products[5], 3]] as [$p, $q]) {
                try {
                    $invoices->save('sale', ['date' => now()->toDateString(), 'party_id' => $walkIn->id, 'warehouse_id' => $wh->id, 'cash_session_id' => $reg->id,
                        'payment_mode' => 'cash', 'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => $q, 'price' => $p->sale_price]],
                        'payments' => [['account_id' => Account::system('cash')->id, 'amount' => $q * $p->sale_price]]]);
                } catch (\Illuminate\Validation\ValidationException) {
                }
            }
            $registers->close($reg, $registers->summary($reg)['expected_cash'] - 20, [], 'Rs 20 short', $owner);

            $vouchers->save('journal', ['date' => now()->subDays(35)->toDateString(), 'narration' => 'Capital introduced',
                'lines' => [['account_id' => Account::system('bank')->id, 'debit' => 500000], ['account_id' => Account::system('capital')->id, 'credit' => 500000]]]);
        });
    }
}
