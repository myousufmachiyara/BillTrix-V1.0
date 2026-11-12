<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StockService;
use App\Services\VoucherService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinalTest extends TestCase
{
    use RefreshDatabase;

    private function balance(string $key): float
    {
        $id = Account::system($key)->id;

        return round((float) JournalLine::where('account_id', $id)->sum('debit') - (float) JournalLine::where('account_id', $id)->sum('credit'), 2);
    }

    private function sale($party, $product, float $qty, array $extra = []): Invoice
    {
        return app(InvoiceService::class)->save('sale', ['date' => now()->toDateString(), 'party_id' => $party->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => $qty, 'price' => 100] + $extra]]);
    }

    public function test_audit_trail_records_changes_signins_and_exports(): void
    {
        $this->setUpBusiness();
        $p = $this->product(['track_stock' => false]);
        $c = $this->party('customer');
        $inv = $this->sale($c, $p, 2);
        app(InvoiceService::class)->save('sale', ['date' => now()->toDateString(), 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 3, 'price' => 100]]], $inv);
        $p->update(['sale_price' => 150]);
        app(InvoiceService::class)->delete($inv->fresh());

        $this->assertTrue(AuditLog::where('event', 'created')->where('auditable_type', 'invoice')->exists());
        $lines = AuditLog::where('event', 'lines_changed')->first();
        $this->assertStringContainsString('2 ×', $lines->new_values['before'][0]);
        $this->assertStringContainsString('3 ×', $lines->new_values['after'][0]);
        $priced = AuditLog::where('event', 'updated')->where('auditable_type', 'product')->first();
        $this->assertEquals(150, $priced->new_values['sale_price']);
        $this->assertSame($inv->number, AuditLog::where('event', 'deleted')->where('auditable_type', 'invoice')->value('label'));

        $this->get('/products?export=xlsx')->assertOk();
        $this->assertTrue(AuditLog::where('event', 'exported')->exists());

        auth()->logout();
        Tenant::forget();
        $this->post('/login', ['login' => $this->owner->email, 'password' => 'wrong']);
        $this->post('/login', ['login' => $this->owner->email, 'password' => 'secret123']);
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('event', 'login_failed')->exists());
        $this->assertTrue(AuditLog::withoutGlobalScopes()->where('event', 'login')->where('user_id', $this->owner->id)->exists());
        // Passwords never reach the log.
        $this->assertFalse(AuditLog::withoutGlobalScopes()->get()->contains(fn ($l) => str_contains(json_encode($l->new_values), 'secret123')));

        $this->get('/settings/audit')->assertOk()->assertSee($inv->number);
        $this->get('/settings/audit?type=invoice&id='.$inv->id)->assertOk();

        $cashier = User::create(['name' => 'C', 'email' => 'c@t.pk', 'password' => 'password1', 'branch_id' => $this->owner->branch_id]);
        $cashier->assignRole('Cashier');
        $this->actingAs($cashier)->get('/settings/audit')->assertForbidden();
    }

    public function test_withholding_tax_on_receipts_and_payments(): void
    {
        $this->setUpBusiness('wholesale', ['wht' => true]);
        $p = $this->product(['track_stock' => false]);
        $c = $this->party('customer');
        $s = $this->party('supplier');
        $sale = $this->sale($c, $p, 100);                      // 10,000 (party has 0 sales WHT rate by default settings)
        $vouchers = app(VoucherService::class);
        $cash = Account::system('cash')->id;

        // Customer pays 9,500 and keeps back 500 tax.
        $vouchers->save('receipt', ['date' => now()->toDateString(), 'cash_account_id' => $cash, 'party_id' => $c->id, 'amount' => 9500, 'wht_amount' => 500, 'wht_rate' => 5,
            'allocations' => [['invoice_id' => $sale->id, 'amount' => 10000]]]);
        $this->assertSame('paid', $sale->fresh()->status);
        $this->assertEquals(0, $c->balance());
        $this->assertEquals(500, $this->balance('wht_receivable'));
        $this->assertEquals(9500, $this->balance('cash'));

        // We owe a supplier 20,000 and deduct 4.5%.
        $pur = app(InvoiceService::class)->save('purchase', ['date' => now()->toDateString(), 'party_id' => $s->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 200, 'price' => 100]]]);
        $owed = -$s->balance();
        $v = $vouchers->save('payment', ['date' => now()->toDateString(), 'cash_account_id' => $cash, 'party_id' => $s->id, 'amount' => $owed - 900, 'wht_amount' => 900,
            'allocations' => [['invoice_id' => $pur->id, 'amount' => $owed]]]);
        $this->assertEquals(0, $s->balance());
        $this->assertEquals(900, (float) $v->fresh()->wht_amount);
        $this->assertEqualsWithDelta((float) JournalLine::sum('debit'), (float) JournalLine::sum('credit'), 0.001);

        $this->get('/reports/withholding')->assertOk()->assertSee($v->number)->assertSee('900.00');
        $this->get("/vouchers/payment/{$v->id}/edit")->assertOk()->assertSee('value="'.($owed - 900).'"', false);
    }

    public function test_sale_can_pick_a_batch_or_use_fefo(): void
    {
        $this->setUpBusiness('wholesale', ['batches' => true]);
        $p = $this->product(['track_batches' => true]);
        $s = $this->party('supplier');
        $c = $this->party('customer');
        foreach ([['B1', 10, 5], ['B2', 90, 5], ['EXP', -1, 5]] as [$batch, $days, $qty]) {
            app(InvoiceService::class)->save('purchase', ['date' => now()->toDateString(), 'party_id' => $s->id, 'warehouse_id' => $this->warehouse->id,
                'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => $qty, 'price' => 50, 'batch_no' => $batch, 'expiry_date' => now()->addDays($days)->toDateString()]]]);
        }
        $stock = fn () => app(StockService::class)->batchStock($p->id)->pluck('quantity', 'batch_no')->map(fn ($q) => (float) $q)->all();

        $this->sale($c, $p, 2, ['batch_no' => 'B2']);            // customer wants the long-dated batch
        $this->assertEquals(['B1' => 5.0, 'B2' => 3.0, 'EXP' => 5.0], $stock());
        $this->sale($c, $p, 1);                                  // no choice → earliest expiry
        $this->assertEquals(4.0, $stock()['B1']);

        $lookup = $this->getJson("/products/lookup?ids={$p->id}&warehouse_id={$this->warehouse->id}")->json('results.0.batches');
        $this->assertSame(['B1', 'B2'], array_column($lookup, 'batch_no'));   // expired batch is not offered

        $this->expectException(ValidationException::class);
        $this->sale($c, $p, 1, ['batch_no' => 'EXP']);
    }

    public function test_backups_and_business_export(): void
    {
        $this->setUpBusiness();
        $this->product(['name' => 'Exported soap']);
        $other = app(\App\Services\BusinessProvisioner::class)->create(['name' => 'Other'], ['name' => 'O', 'email' => 'o@o.pk', 'password' => 'password1']);
        Tenant::run($other->business_id, fn () => $this->product(['name' => 'Not mine']));
        $this->actingAs($this->owner);
        Tenant::set($this->owner->business_id);

        $res = $this->get('/settings/backup/download')->assertOk()->assertDownload();
        $zip = new \ZipArchive;
        $zip->open($res->baseResponse->getFile()->getPathname());
        $products = json_decode($zip->getFromName('data/products.json'), true);
        $users = $zip->getFromName('data/users.json');
        $this->assertSame(['Exported soap'], array_column($products, 'name'));
        $this->assertStringNotContainsString('password', $users);
        $this->assertNotFalse($zip->getFromName('data/journal_lines.json'));

        \Illuminate\Support\Facades\Storage::fake('local');
        $this->artisan('billtrix:backup')->assertSuccessful();
        $files = \Illuminate\Support\Facades\Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertGreaterThan(100, \Illuminate\Support\Facades\Storage::disk('local')->size($files[0]));
        $this->get('/settings/backup')->assertOk()->assertSee(basename($files[0]));
    }

    public function test_offline_pos_bills_are_saved_once_with_their_own_date(): void
    {
        $this->setUpBusiness('retail', ['pos_shifts' => false]);
        $p = $this->product(['barcode' => '8960001']);
        app(InvoiceService::class)->save('purchase', ['date' => now()->subDays(3)->toDateString(), 'party_id' => $this->party('supplier')->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 10, 'price' => 40]]]);
        $walkIn = \App\Models\Party::where('is_walk_in', true)->first();

        $catalog = $this->getJson('/pos/catalog?warehouse_id='.$this->warehouse->id)->assertOk()->json('products');
        $row = collect($catalog)->firstWhere('id', $p->id);
        $this->assertSame('8960001', $row['barcode']);
        $this->assertEquals(10, $row['stock']);

        $uuid = (string) \Illuminate\Support\Str::uuid();
        $body = ['party_id' => $walkIn->id, 'warehouse_id' => $this->warehouse->id, 'client_uuid' => $uuid, 'sold_at' => now()->subDay()->toDateString(),
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 2, 'price' => 100]],
            'payments' => [['account_id' => Account::system('cash')->id, 'amount' => 200]]];

        $first = $this->postJson('/pos', $body)->assertOk()->json();
        $again = $this->postJson('/pos', $body)->assertOk()->json();   // the device retried after a dropped connection
        $this->assertSame($first['id'], $again['id']);
        $this->assertTrue($again['duplicate']);
        $this->assertSame(1, Invoice::where('client_uuid', $uuid)->count());
        $this->assertSame(now()->subDay()->toDateString(), Invoice::find($first['id'])->date->toDateString());
        $this->assertEquals(8, $p->fresh()->balances()->sum('quantity'));

        // A bill can't be back-dated more than a week or dated in the future.
        $this->postJson('/pos', ['client_uuid' => (string) \Illuminate\Support\Str::uuid(), 'sold_at' => now()->subDays(10)->toDateString()] + $body)->assertStatus(422);
        $this->postJson('/pos', ['client_uuid' => (string) \Illuminate\Support\Str::uuid(), 'sold_at' => now()->addDay()->toDateString()] + $body)->assertStatus(422);

        $this->get('/pos')->assertOk()->assertSee('manifest.webmanifest')->assertSee('queueModal', false);
    }

    public function test_sign_in_with_username_or_email(): void
    {
        $this->setUpBusiness();
        $this->artisan('billtrix:set-login', ['username' => 'admin', '--user' => $this->owner->email, '--password' => '12345678'])->assertSuccessful();
        auth()->logout();

        $this->post('/login', ['login' => 'admin', 'password' => 'wrong-pass'])->assertSessionHasErrors('login');
        $this->post('/login', ['login' => 'admin', 'password' => '12345678'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->owner->fresh());
        auth()->logout();
        $this->post('/login', ['login' => $this->owner->email, 'password' => '12345678'])->assertRedirect(route('dashboard'));

        $this->postJson('/api/v1/auth/login', ['login' => 'admin', 'password' => '12345678', 'device_name' => 'phone'])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/v1/auth/login', ['email' => $this->owner->email, 'password' => '12345678', 'device_name' => 'phone'])->assertOk();
    }
}
