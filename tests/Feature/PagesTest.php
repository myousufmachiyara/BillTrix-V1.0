<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Party;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenant;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Renders every screen against realistic demo data and exercises the main forms over HTTP. */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoSeeder::class);
        Tenant::forget();
        $this->user = User::withoutGlobalScopes()->where('email', 'demo@billtrix.pk')->firstOrFail();
        $this->actingAs($this->user);
        Tenant::set($this->user->business_id);
        Warehouse::query()->update(['is_active' => true]);
        $b = $this->user->business;
        $b->settings = array_merge($b->settings ?? [], ['multi_warehouse' => true, 'tax' => true, 'price_levels' => true, 'multi_unit' => true, 'pos' => true, 'pos_shifts' => false]);
        $b->save();
        Tenant::set($b->fresh());
    }

    public function test_every_screen_renders(): void
    {
        $sale = Invoice::where('type', 'sale')->firstOrFail();
        $purchase = Invoice::where('type', 'purchase')->firstOrFail();
        $party = Party::customers()->where('is_walk_in', false)->firstOrFail();
        $product = Product::firstOrFail();
        $receipt = JournalEntry::where('type', 'receipt')->whereNull('source_type')->firstOrFail();
        $journal = JournalEntry::where('type', 'journal')->firstOrFail();
        $adj = StockAdjustment::first();
        $acc = Account::system('cash');

        $urls = [
            '/', '/settings/business', '/settings/branches', '/settings/users', '/settings/users/create', "/settings/users/{$this->user->id}/edit",
            '/settings/roles', '/settings/roles/create', '/masters/units', '/masters/categories', '/masters/price-levels', '/masters/tax-rates',
            '/accounts', '/accounts/create', "/accounts/{$acc->id}/edit",
            '/parties?type=customer', '/parties?type=supplier', '/parties/create?type=customer', "/parties/{$party->id}", "/parties/{$party->id}/edit",
            '/parties/lookup?q=a&type=customer', "/parties/{$party->id}/open-invoices",
            '/vouchers/receipt', '/vouchers/payment', '/vouchers/journal', '/vouchers/contra',
            '/vouchers/receipt/create', "/vouchers/receipt/create?party_id={$party->id}", '/vouchers/payment/create', '/vouchers/journal/create', '/vouchers/contra/create',
            "/vouchers/receipt/{$receipt->id}", "/vouchers/receipt/{$receipt->id}/edit", "/vouchers/journal/{$journal->id}", "/vouchers/journal/{$journal->id}/edit",
            '/products', '/products?status=low', '/products/create', "/products/{$product->id}", "/products/{$product->id}/edit", '/products/lookup?q=oil',
            '/stock-adjustments', '/stock-adjustments/create', '/stock-adjustments/create?type=opening', '/stock-adjustments/create?type=count',
            '/stock-transfers', '/stock-transfers/create',
            '/sales', '/sales/create', "/sales/{$sale->id}", "/sales/{$sale->id}/edit", "/sales/{$sale->id}/print", "/sales/{$sale->id}/print?layout=thermal",
            '/purchases', '/purchases/create', "/purchases/{$purchase->id}", "/purchases/{$purchase->id}/print",
            '/sale-returns', "/sale-returns/create?from={$sale->id}", '/purchase-returns', "/purchase-returns/create?from={$purchase->id}",
            '/reports', '/reports/trial-balance', '/reports/profit-loss', '/reports/balance-sheet', "/reports/ledger?account_id={$acc->id}", '/reports/cash-book',
            "/reports/party-statement?party_id={$party->id}", '/reports/aging', '/reports/aging?side=payable', '/reports/day-book', '/reports/stock',
            "/reports/stock-movement?product_id={$product->id}", '/reports/low-stock', '/reports/register', '/reports/register?type=purchase', '/reports/product-sales',
        ];
        $so = \App\Models\Order::where('type', 'sale_order')->first();
        $qt = \App\Models\Order::where('type', 'quotation')->first();
        $po = \App\Models\Order::where('type', 'purchase_order')->first();
        $dc = \App\Models\Delivery::first();
        $reg = \App\Models\CashSession::first();
        $urls = array_merge($urls, [
            '/quotations', '/quotations/create', "/quotations/{$qt->id}", '/quotations/'.\App\Models\Order::where('type', 'quotation')->where('status', 'open')->value('id').'/edit', "/quotations/{$qt->id}/print",
            '/sales-orders', '/sales-orders/create', "/sales-orders/create?quotation={$qt->id}", "/sales-orders/{$so->id}", "/sales-orders/{$so->id}/print",
            '/purchase-orders', '/purchase-orders/create', "/purchase-orders/{$po->id}", "/purchase-orders/{$po->id}/edit",
            "/sales/create?order={$so->id}", "/purchases/create?order={$po->id}", "/sales/create?quotation={$qt->id}",
            '/deliveries', '/deliveries/create', "/deliveries/create?order={$so->id}", '/deliveries?status=pending',
            '/pos', '/pos/recent', '/cash-sessions', "/cash-sessions/{$reg->id}", "/cash-sessions/{$reg->id}/print",
            "/parties/{$party->id}", '/products/lookup?q=a&party_id='.$party->id.'&category_id=1',
        ]);
        \Illuminate\Support\Facades\Http::fake(); // never reach FBR from tests
        $b = $this->user->business->fresh();
        $b->update(['settings' => array_merge($b->settings ?? [], ['fbr_mode' => 'pos', 'wht' => true, 'batches' => true])]);
        Tenant::set($b->fresh());
        $urls = array_merge($urls, [
            '/fbr', '/fbr?status=failed', '/imports/products', '/imports/parties', '/imports/parties/template',
            '/reports/withholding', '/reports/expiry', '/products?export=xlsx',
            '/settings/audit', '/settings/audit?type=invoice', '/settings/backup', '/pos/catalog',
        ]);
        if ($dc) {
            $urls = array_merge($urls, ["/deliveries/{$dc->id}", "/deliveries/{$dc->id}/edit", "/deliveries/{$dc->id}/print", "/sales/create?deliveries={$dc->id}"]);
        }
        if ($adj) {
            $urls[] = "/stock-adjustments/{$adj->id}";
        }

        foreach ($urls as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->baseResponse->getStatusCode(), "GET {$url} returned {$res->baseResponse->getStatusCode()}: ".substr(strip_tags((string) $res->baseResponse->getContent()), 0, 600));
        }

        // Every screen again in Urdu.
        $this->user->update(['locale' => 'ur']);
        foreach ($urls as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->baseResponse->getStatusCode(), "GET {$url} (ur) returned {$res->baseResponse->getStatusCode()}: ".substr(strip_tags((string) $res->baseResponse->getContent()), 0, 600));
        }
        $this->get('/products')->assertSee('پروڈکٹ', false);
        $this->get('/reports/profit-loss')->assertSee('نفع و نقصان', false);
    }

    public function test_books_balance_after_demo_data(): void
    {
        $tb = app(\App\Services\ReportService::class)->trialBalance(now()->toDateString());
        $this->assertEqualsWithDelta($tb['debit'], $tb['credit'], 0.001);

        $bs = app(\App\Services\ReportService::class)->balanceSheet(now()->toDateString());
        $this->assertEqualsWithDelta($bs['asset_total'], $bs['liability_total'] + $bs['equity_total'], 0.01);

        // Inventory account equals stock valued at movement cost.
        $stockValue = \App\Models\StockMovement::selectRaw('SUM(quantity * unit_cost) as v')->value('v');
        $this->assertEqualsWithDelta((float) $stockValue, (float) \App\Models\JournalLine::where('account_id', Account::system('inventory')->id)->selectRaw('SUM(debit) - SUM(credit) as b')->value('b'), 1.0);
    }

    public function test_sale_over_http_and_return(): void
    {
        $p = Product::whereHas('balances', fn ($q) => $q->where('quantity', '>=', 10))->firstOrFail();
        $customer = Party::customers()->where('is_walk_in', false)->firstOrFail();
        $wh = Warehouse::firstOrFail();
        $stockBefore = $p->stockIn();

        $this->post('/sales', [
            'date' => now()->toDateString(), 'party_id' => $customer->id, 'warehouse_id' => $wh->id, 'payment_mode' => 'credit',
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 2, 'price' => 160, 'discount_percent' => 0, 'tax_rate' => 0]],
            'paid_amount' => 100, 'payment_account_id' => Account::system('cash')->id, 'after' => 'view',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $sale = Invoice::where('type', 'sale')->latest('id')->first();
        $this->assertEquals(320, (float) $sale->total);
        $this->assertEquals(100, (float) $sale->paid_amount);
        $this->assertEqualsWithDelta($stockBefore - 2, $p->fresh()->stockIn(), 0.0001);

        $this->post('/sale-returns', [
            'date' => now()->toDateString(), 'party_id' => $customer->id, 'warehouse_id' => $wh->id, 'return_of_id' => $sale->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 1, 'price' => 160, 'source_item_id' => $sale->items()->value('id')]],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(260, (float) $sale->fresh()->paid_amount);

        // Validation error keeps the user on the form with their lines.
        $this->from('/sales/create')->post('/sales', ['date' => now()->toDateString(), 'party_id' => $customer->id, 'warehouse_id' => $wh->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 99999, 'price' => 160]]])
            ->assertRedirect('/sales/create')->assertSessionHasErrors();
        $this->get('/sales/create')->assertOk()->assertSee($p->name);
    }

    public function test_cashier_cannot_open_accounts_or_settings(): void
    {
        $cashier = User::create(['name' => 'Cash', 'email' => 'cash@x.pk', 'password' => 'password1', 'branch_id' => $this->user->branch_id]);
        $cashier->assignRole('Cashier');
        $this->actingAs($cashier);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->get('/sales/create')->assertOk();
        $this->get('/accounts')->assertForbidden();
        $this->get('/settings/business')->assertForbidden();
        $this->get('/reports/profit-loss')->assertForbidden();
    }

    public function test_businesses_are_isolated(): void
    {
        $other = app(\App\Services\BusinessProvisioner::class)->create(['name' => 'Other Co'], ['name' => 'O', 'email' => 'o@o.pk', 'password' => 'password1']);
        Tenant::forget();
        $this->actingAs($other);
        $sale = Invoice::withoutGlobalScopes()->where('business_id', $this->user->business_id)->first();
        $this->get("/sales/{$sale->id}")->assertNotFound();
        $this->get('/products')->assertOk()->assertDontSee('Cooking Oil');
    }

    public function test_real_login_then_dashboard(): void
    {
        auth()->logout();
        Tenant::forget();
        $this->post('/login', ['email' => 'demo@billtrix.pk', 'password' => 'password'])->assertRedirect('/');
        $this->app['auth']->forgetGuards();
        Tenant::forget(); // a new request starts with no tenant; the session user must be resolved without recursion
        $this->get('/')->assertOk()->assertSee('Al-Noor Traders');
    }

    public function test_registration_creates_a_ready_business(): void
    {
        auth()->logout();
        Tenant::forget();
        $this->post('/register', ['business_name' => 'New Shop', 'category' => 'retail', 'name' => 'Ali', 'email' => 'ali@shop.pk',
            'password' => 'password123', 'password_confirmation' => 'password123'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('New Shop');
    }
}
