<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Order;
use App\Services\CashSessionService;
use App\Services\DeliveryService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase2Test extends TestCase
{
    use RefreshDatabase;

    private function balance(string $key): float
    {
        $id = Account::system($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    private function stockUp($product, float $qty, float $cost): void
    {
        app(InvoiceService::class)->save('purchase', ['date' => '2026-11-01', 'party_id' => $this->party('supplier')->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => $qty, 'price' => $cost]]]);
    }

    private function order(string $type, $party, $product, float $qty, float $price): Order
    {
        return app(OrderService::class)->save($type, ['date' => '2026-11-02', 'party_id' => $party->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => $qty, 'price' => $price]]]);
    }

    public function test_sales_order_partial_challan_then_invoice_from_challan(): void
    {
        $this->setUpBusiness();
        $p = $this->product();
        $c = $this->party('customer');
        $this->stockUp($p, 50, 40);
        $so = $this->order('sale_order', $c, $p, 30, 100);
        $this->assertSame('open', $so->status);

        $dc = app(DeliveryService::class)->save(['date' => '2026-11-03', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id, 'order_id' => $so->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 10, 'order_item_id' => $so->items[0]->id]]]);

        $this->assertSame('partial', $so->fresh()->status);
        $this->assertEquals(40.0, app(StockService::class)->onHand($p->id));
        $this->assertEquals(400, $this->balance('gdni'));
        $this->assertEquals(0, $c->balance()); // nothing billed yet

        // Can't deliver more than pending.
        try {
            app(DeliveryService::class)->save(['date' => '2026-11-03', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id, 'order_id' => $so->id,
                'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 21, 'order_item_id' => $so->items[0]->id]]]);
            $this->fail('over-delivery allowed');
        } catch (ValidationException) {
        }

        $inv = app(InvoiceService::class)->save('sale', ['date' => '2026-11-04', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 10, 'price' => 100, 'delivery_item_id' => $dc->items[0]->id]]]);

        $this->assertEquals(40.0, app(StockService::class)->onHand($p->id)); // no second stock issue
        $this->assertEquals(0, $this->balance('gdni'));
        $this->assertEquals(400, $this->balance('cogs'));
        $this->assertEquals(1000, $c->balance());
        $this->assertSame($inv->id, $dc->fresh()->invoice_id);
        $this->assertSame($so->id, $inv->order_id);
        $this->assertSame('partial', $so->fresh()->status);

        // Direct invoice for the rest completes the order.
        app(InvoiceService::class)->save('sale', ['date' => '2026-11-05', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 20, 'price' => 100, 'order_item_id' => $so->items[0]->id]]]);
        $this->assertSame('completed', $so->fresh()->status);

        // Deleting the challan's invoice re-opens the challan; stock is unchanged.
        app(InvoiceService::class)->delete($inv->fresh());
        $this->assertNull($dc->fresh()->invoice_id);
        $this->assertEquals(400, $this->balance('gdni'));
        $this->assertEquals(20.0, app(StockService::class)->onHand($p->id));
        $this->assertSame('completed', $so->fresh()->status); // still fully delivered/invoiced

        app(DeliveryService::class)->delete($dc->fresh());
        $this->assertEquals(0, $this->balance('gdni'));
        $this->assertEquals(30.0, app(StockService::class)->onHand($p->id));
        $this->assertSame('partial', $so->fresh()->status);
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);
    }

    public function test_challan_must_be_invoiced_whole_and_once(): void
    {
        $this->setUpBusiness();
        $p = $this->product();
        $c = $this->party('customer');
        $this->stockUp($p, 20, 40);
        $dc = app(DeliveryService::class)->save(['date' => '2026-11-03', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 5]]]);

        $bill = fn ($qty) => app(InvoiceService::class)->save('sale', ['date' => '2026-11-04', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => $qty, 'price' => 100, 'delivery_item_id' => $dc->items[0]->id]]]);

        $this->expectException(ValidationException::class);
        try {
            $bill(4);
        } finally {
            $bill(5);
            $this->assertNotNull($dc->fresh()->invoice_id);
            $bill(5); // second time must fail
        }
    }

    public function test_purchase_order_partial_receipt_through_purchase_invoice(): void
    {
        $this->setUpBusiness();
        $p = $this->product();
        $s = $this->party('supplier');
        $po = $this->order('purchase_order', $s, $p, 100, 50);
        app(InvoiceService::class)->save('purchase', ['date' => '2026-11-03', 'party_id' => $s->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 60, 'price' => 50, 'order_item_id' => $po->items[0]->id]]]);
        $po->refresh()->load('items');
        $this->assertSame('partial', $po->status);
        $this->assertEquals(40.0, $po->items[0]->pending);

        // Ordered quantity can't drop below what was received.
        $this->expectException(ValidationException::class);
        app(OrderService::class)->save('purchase_order', ['date' => '2026-11-02', 'party_id' => $s->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['id' => $po->items[0]->id, 'product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 50, 'price' => 50]]], $po);
    }

    public function test_quotation_converts_to_sales_order(): void
    {
        $this->setUpBusiness();
        $p = $this->product();
        $c = $this->party('customer');
        $qt = $this->order('quotation', $c, $p, 5, 100);
        $so = app(OrderService::class)->save('sale_order', ['date' => '2026-11-05', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id, 'quotation_id' => $qt->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 5, 'price' => 100]]]);
        $this->assertSame('converted', $qt->fresh()->status);
        $this->assertSame($qt->id, $so->quotation_id);
        $this->assertEquals(0, JournalLine::count());
    }

    public function test_cash_session_split_payment_and_over_short(): void
    {
        $this->setUpBusiness('retail');
        $p = $this->product();
        $this->stockUp($p, 10, 40);
        $cash = Account::system('cash');
        $bank = Account::system('bank');
        $walkIn = \App\Models\Party::where('is_walk_in', true)->first();
        $svc = app(CashSessionService::class);
        $session = $svc->open($this->owner, $cash->id, 2000);

        app(InvoiceService::class)->save('sale', ['date' => now()->toDateString(), 'party_id' => $walkIn->id, 'warehouse_id' => $this->warehouse->id,
            'cash_session_id' => $session->id, 'payment_mode' => 'cash',
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 3, 'price' => 100]],
            'payments' => [['account_id' => $cash->id, 'amount' => 200], ['account_id' => $bank->id, 'amount' => 100]]]);

        $sum = $svc->summary($session);
        $this->assertSame(1, $sum['sales_count']);
        $this->assertEquals(300, $sum['sales_total']);
        $this->assertEquals(2200, $sum['expected_cash']);
        $this->assertEquals(100, $this->balance('bank'));

        $closed = $svc->close($session, 2150, [1000 => 2, 100 => 1, 50 => 1], 'short 50', $this->owner);
        $this->assertEquals(-50, (float) $closed->difference);
        $this->assertEquals(50, $this->balance('cash_over_short'));
        $this->assertEquals(150, $this->balance('cash'));

        $this->expectException(ValidationException::class);
        $svc->close($closed, 2150, [], null, $this->owner);
    }

    public function test_upgrade_adds_new_system_accounts_to_old_businesses(): void
    {
        $this->setUpBusiness();
        Account::system('gdni')->delete();
        Account::flushSystemCache();
        app(\App\Services\BusinessProvisioner::class)->upgrade($this->owner->business);
        Account::flushSystemCache();
        $this->assertNotNull(Account::system('gdni'));
        app(\App\Services\BusinessProvisioner::class)->upgrade($this->owner->business); // idempotent
        $this->assertSame(1, Account::where('system_key', 'gdni')->count());
    }

    public function test_pos_over_http_requires_open_register_and_takes_split_payment(): void
    {
        $this->setUpBusiness('retail');
        $p = $this->product();
        $this->stockUp($p, 10, 40);
        $walkIn = \App\Models\Party::where('is_walk_in', true)->first();
        $body = ['party_id' => $walkIn->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 2, 'price' => 100]],
            'payments' => [['account_id' => Account::system('cash')->id, 'amount' => 150], ['account_id' => Account::system('bank')->id, 'amount' => 50]]];

        $this->get('/pos')->assertRedirect('/cash-sessions');
        $this->postJson('/pos', $body)->assertStatus(409);

        $this->post('/cash-sessions', ['account_id' => Account::system('cash')->id, 'opening_float' => 1000])->assertRedirect('/pos');
        $this->get('/pos')->assertOk()->assertSee('Complete sale');
        $res = $this->postJson('/pos', $body)->assertOk()->json();
        $this->assertEquals(200, $res['total']);
        $inv = Invoice::find($res['id']);
        $this->assertSame('paid', $inv->status);
        $this->assertNotNull($inv->cash_session_id);

        // Walk-in can't leave a balance.
        $body['payments'] = [['account_id' => Account::system('cash')->id, 'amount' => 100]];
        $this->postJson('/pos', $body)->assertStatus(422);

        $session = \App\Models\CashSession::first();
        $this->get("/cash-sessions/{$session->id}/close")->assertOk()->assertSee('1,150.00');
        $this->put("/cash-sessions/{$session->id}/close", ['counted_cash' => 1150, 'denominations' => [1000 => 1, 100 => 1, 50 => 1]])->assertRedirect();
        $this->assertEquals(0, (float) $session->fresh()->difference);
        $this->get('/pos')->assertRedirect('/cash-sessions');
    }

    public function test_customer_special_price_wins_in_lookup(): void
    {
        $this->setUpBusiness('wholesale');
        $p = $this->product(['sale_price' => 100]);
        $c = $this->party('customer');
        $this->post("/parties/{$c->id}/prices", ['product_id' => $p->id, 'price' => 88])->assertRedirect();
        $r = $this->getJson("/products/lookup?ids={$p->id}&party_id={$c->id}")->json('results.0');
        $this->assertEquals(88, $r['sale_price']);
        $this->assertTrue($r['special_price']);
        $other = $this->party('customer');
        $this->assertEquals(100, $this->getJson("/products/lookup?ids={$p->id}&party_id={$other->id}")->json('results.0.sale_price'));
    }

    public function test_order_and_challan_forms_over_http(): void
    {
        $this->setUpBusiness('wholesale');
        $p = $this->product();
        $c = $this->party('customer');
        $this->stockUp($p, 30, 40);
        $this->post('/sales-orders', ['date' => '2026-11-05', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 20, 'price' => 100]]])->assertSessionHasNoErrors();
        $so = Order::where('type', 'sale_order')->first();
        $this->get("/deliveries/create?order={$so->id}")->assertOk()->assertSee($p->name);
        $this->post('/deliveries', ['date' => '2026-11-06', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id, 'order_id' => $so->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 8, 'order_item_id' => $so->items[0]->id]]])->assertSessionHasNoErrors();
        $dc = Delivery::first();
        $this->get("/sales/create?deliveries={$dc->id}")->assertOk()->assertSee($dc->number);
        $this->get("/sales/create?order={$so->id}")->assertOk()->assertSee('pending');
        $this->post('/sales', ['date' => '2026-11-07', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id, 'after' => 'view',
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 8, 'price' => 100, 'delivery_item_id' => $dc->items[0]->id]]])->assertSessionHasNoErrors();
        $this->assertNotNull($dc->fresh()->invoice_id);
        $this->get("/sales-orders/{$so->id}")->assertOk()->assertSee('Partly fulfilled');
        $this->put("/sales-orders/{$so->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $so->fresh()->status);
    }
}
