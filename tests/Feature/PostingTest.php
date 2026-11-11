<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\ProductUnit;
use App\Models\Unit;
use App\Services\InvoiceService;
use App\Services\StockDocumentService;
use App\Services\VoucherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PostingTest extends TestCase
{
    use RefreshDatabase;

    private function invoices(): InvoiceService
    {
        return app(InvoiceService::class);
    }

    private function balance(string $key): float
    {
        $a = Account::system($key);

        return round((float) JournalLine::where('account_id', $a->id)->sum('debit') - (float) JournalLine::where('account_id', $a->id)->sum('credit'), 2);
    }

    private function assertLedgerBalanced(): void
    {
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);
    }

    private function buy($supplier, $product, float $qty, float $price, array $extra = []): Invoice
    {
        return $this->invoices()->save('purchase', $extra + [
            'date' => '2026-11-01', 'party_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => $qty, 'price' => $price]],
        ]);
    }

    public function test_purchase_and_sale_post_balanced_entries_with_weighted_average_cost(): void
    {
        $this->setUpBusiness();
        $supplier = $this->party('supplier');
        $customer = $this->party('customer');
        $p = $this->product();

        $this->buy($supplier, $p, 10, 50);
        $this->buy($supplier, $p, 10, 70);
        $this->assertEqualsWithDelta(60, (float) $p->fresh()->avg_cost, 0.0001);
        $this->assertEquals(-1200, $this->balance('payable'));
        $this->assertEquals(1200, $this->balance('inventory'));

        $sale = $this->invoices()->save('sale', [
            'date' => '2026-11-02', 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 5, 'price' => 100, 'discount_percent' => 10]],
            'discount' => 50, 'paid_amount' => 100,
        ]);

        $this->assertEquals(400, (float) $sale->total);          // 450 - 50
        $this->assertEquals(300, (float) $sale->cost_total);     // 5 × 60
        $this->assertEquals(100, (float) $sale->paid_amount);
        $this->assertEquals('partial', $sale->status);
        $this->assertEquals(300, $customer->balance());
        $this->assertEquals(900, $this->balance('inventory'));
        $this->assertEquals(-450, $this->balance('sales'));
        $this->assertEquals(50, $this->balance('sales_discount'));
        $this->assertEquals(300, $this->balance('cogs'));
        $this->assertEquals(15.0, app(\App\Services\StockService::class)->onHand($p->id));
        $this->assertLedgerBalanced();
    }

    public function test_editing_an_invoice_reposts_stock_and_ledger(): void
    {
        $this->setUpBusiness();
        $p = $this->product();
        $supplier = $this->party('supplier');
        $inv = $this->buy($supplier, $p, 10, 50);
        $this->invoices()->save('purchase', [
            'date' => '2026-11-01', 'party_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 4, 'price' => 55]],
        ], $inv);

        $this->assertEquals(4.0, app(\App\Services\StockService::class)->onHand($p->id));
        $this->assertEquals(220, $this->balance('inventory'));
        $this->assertEqualsWithDelta(55, (float) $p->fresh()->avg_cost, 0.0001);
        $this->assertSame(1, Invoice::count());
        $this->assertLedgerBalanced();

        $this->invoices()->delete($inv->fresh());
        $this->assertEquals(0.0, app(\App\Services\StockService::class)->onHand($p->id));
        $this->assertEquals(0, JournalLine::count());
    }

    public function test_cartons_convert_to_base_units_and_tax_is_posted(): void
    {
        $this->setUpBusiness('wholesale', ['tax' => true]);
        $p = $this->product();
        $ctn = Unit::where('short_name', 'ctn')->first();
        ProductUnit::create(['product_id' => $p->id, 'unit_id' => $ctn->id, 'factor' => 24]);
        $supplier = $this->party('supplier');

        $inv = $this->invoices()->save('purchase', [
            'date' => '2026-11-01', 'party_id' => $supplier->id, 'warehouse_id' => $this->warehouse->id, 'other_charges' => 120,
            'items' => [['product_id' => $p->id, 'unit_id' => $ctn->id, 'quantity' => 2, 'price' => 2400, 'tax_rate' => 18]],
        ]);

        $this->assertEquals(48.0, app(\App\Services\StockService::class)->onHand($p->id));
        $this->assertEquals(864, (float) $inv->tax_total);
        $this->assertEquals(4800 + 864 + 120, (float) $inv->total);
        $this->assertEquals(102.5, (float) $p->fresh()->avg_cost); // (4800 + 120 freight) / 48
        $this->assertEquals(864, $this->balance('tax_input'));
        $this->assertLedgerBalanced();
    }

    public function test_stock_and_credit_limit_guards(): void
    {
        $this->setUpBusiness('wholesale');
        $p = $this->product();
        $customer = $this->party('customer', ['credit_limit' => 500]);
        $this->buy($this->party('supplier'), $p, 10, 50);

        $sell = fn ($qty) => $this->invoices()->save('sale', [
            'date' => '2026-11-02', 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => $qty, 'price' => 100]],
        ]);

        try {
            $sell(11);
            $this->fail('Expected stock error');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Not enough stock', json_encode($e->errors()));
        }

        // Owner can override; a role without that permission cannot.
        $cashier = \App\Models\User::create(['name' => 'C', 'email' => 'c@t.pk', 'password' => 'x', 'branch_id' => $this->owner->branch_id]);
        $cashier->assignRole('Cashier');
        $this->actingAs($cashier);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $sell(5); // 500 = at the limit
        try {
            $sell(1);
            $this->fail('Expected credit limit error');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Credit limit exceeded', json_encode($e->errors()));
        }
        $this->assertDatabaseCount('invoices', 2); // failed sales rolled back
        $this->assertLedgerBalanced();
    }

    public function test_sale_return_restores_stock_at_original_cost_and_settles_invoice(): void
    {
        $this->setUpBusiness();
        $p = $this->product();
        $customer = $this->party('customer');
        $this->buy($this->party('supplier'), $p, 10, 40);
        $sale = $this->invoices()->save('sale', [
            'date' => '2026-11-02', 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 4, 'price' => 100]],
        ]);
        $this->buy($this->party('supplier'), $p, 6, 80); // avg moves

        $ret = $this->invoices()->save('sale_return', [
            'date' => '2026-11-03', 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id, 'return_of_id' => $sale->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 1, 'price' => 100, 'source_item_id' => $sale->items[0]->id]],
        ]);

        $this->assertEquals(40.0, (float) $ret->items[0]->unit_cost);
        $this->assertEquals(100, (float) $sale->fresh()->paid_amount);
        $this->assertEquals(300, $customer->balance());
        $this->assertEquals('paid', $ret->fresh()->status);

        $this->expectException(ValidationException::class);
        $this->invoices()->save('sale_return', [
            'date' => '2026-11-03', 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id, 'return_of_id' => $sale->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 4, 'price' => 100, 'source_item_id' => $sale->items[0]->id]],
        ]);
    }

    public function test_receipt_voucher_allocates_to_invoices(): void
    {
        $this->setUpBusiness();
        $p = $this->product(['track_stock' => false]);
        $customer = $this->party('customer');
        $sale = $this->invoices()->save('sale', [
            'date' => '2026-11-02', 'party_id' => $customer->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 10, 'price' => 100]],
        ]);

        $v = app(VoucherService::class)->save('receipt', [
            'date' => '2026-11-05', 'cash_account_id' => Account::system('cash')->id, 'party_id' => $customer->id, 'amount' => 700,
            'allocations' => [['invoice_id' => $sale->id, 'amount' => 600]],
        ]);
        $this->assertEquals(600, (float) $sale->fresh()->paid_amount);
        $this->assertEquals(300, $customer->balance());
        $this->assertEquals(700, $this->balance('cash'));

        app(VoucherService::class)->delete($v);
        $this->assertEquals(0, (float) $sale->fresh()->paid_amount);
        $this->assertLedgerBalanced();
    }

    public function test_opening_stock_count_and_transfer(): void
    {
        $this->setUpBusiness();
        $p = $this->product();
        $svc = app(StockDocumentService::class);
        $second = \App\Models\Warehouse::create(['branch_id' => $this->warehouse->branch_id, 'name' => 'Godown']);

        $svc->adjust(['warehouse_id' => $this->warehouse->id, 'date' => '2026-11-01', 'type' => 'opening',
            'items' => [['product_id' => $p->id, 'quantity' => 20, 'unit_cost' => 30]]]);
        $this->assertEquals(600, $this->balance('inventory'));
        $this->assertEquals(-600, $this->balance('opening_equity'));

        $svc->transfer(['from_warehouse_id' => $this->warehouse->id, 'to_warehouse_id' => $second->id, 'date' => '2026-11-02',
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 5]]]);
        $stock = app(\App\Services\StockService::class);
        $this->assertEquals(15.0, $stock->onHand($p->id, $this->warehouse->id));
        $this->assertEquals(5.0, $stock->onHand($p->id, $second->id));

        $svc->adjust(['warehouse_id' => $this->warehouse->id, 'date' => '2026-11-03', 'type' => 'count',
            'items' => [['product_id' => $p->id, 'counted' => 12]]]);
        $this->assertEquals(12.0, $stock->onHand($p->id, $this->warehouse->id));
        $this->assertEquals(510, $this->balance('inventory'));
        $this->assertEquals(90, $this->balance('stock_adjustment'));
        $this->assertLedgerBalanced();
    }
}
