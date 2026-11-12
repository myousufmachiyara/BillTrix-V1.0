<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\BusinessProvisioner;
use App\Services\InvoiceService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private function token(string $email, string $password = 'secret123'): string
    {
        $res = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password, 'device_name' => 'Galaxy A54'])->assertOk();
        $this->app['auth']->forgetGuards();
        Tenant::forget();

        return $res->json('token');
    }

    public function test_login_catalog_sale_order_receipt_and_logout(): void
    {
        $this->setUpBusiness('wholesale');
        $p = $this->product(['barcode' => '899000111']);
        $c = $this->party('customer');
        app(InvoiceService::class)->save('purchase', ['date' => now()->toDateString(), 'party_id' => $this->party('supplier')->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 20, 'price' => 50]]]);
        $email = $this->owner->email;
        auth()->logout();
        Tenant::forget();

        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong', 'device_name' => 'x'])->assertStatus(422);
        $token = $this->token($email);
        $h = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/auth/me', $h)->assertOk()->assertJsonPath('business.name', 'Test Traders')->assertJsonFragment(['sales.create']);
        $this->getJson('/api/v1/lookups', $h)->assertOk()->assertJsonStructure(['categories', 'units', 'warehouses', 'price_levels', 'payment_accounts']);
        $this->getJson('/api/v1/products?barcode=899000111&warehouse_id='.$this->warehouse->id, $h)->assertOk()
            ->assertJsonPath('data.0.id', $p->id)->assertJsonPath('data.0.stock', 20)->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson("/api/v1/products/{$p->id}", $h)->assertOk()->assertJsonPath('stock.0.quantity', 20);

        $order = $this->postJson('/api/v1/orders', ['type' => 'sale_order', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 5, 'price' => 100]]], $h)->assertCreated()->json('data');
        $this->assertSame('open', $order['status']);

        $inv = $this->postJson('/api/v1/invoices', ['type' => 'sale', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 5, 'price' => 100, 'order_item_id' => $order['items'][0]['id']]]], $h)
            ->assertCreated()->json('data');
        $this->assertEquals(500, $inv['total']);
        $this->getJson("/api/v1/orders/{$order['id']}", $h)->assertJsonPath('data.status', 'completed');

        $this->postJson('/api/v1/receipts', ['party_id' => $c->id, 'cash_account_id' => Tenant::run($this->owner->business_id, fn () => Account::system('cash')->id),
            'amount' => 300, 'allocations' => [['invoice_id' => $inv['id'], 'amount' => 300]]], $h)->assertCreated();
        $this->getJson("/api/v1/parties/{$c->id}", $h)->assertOk()->assertJsonPath('data.balance', 200)->assertJsonPath('open_invoices.0.balance', 200);
        $this->getJson("/api/v1/parties/{$c->id}/statement", $h)->assertOk()->assertJsonPath('closing', 200);
        $this->getJson('/api/v1/invoices?type=sale&outstanding=1', $h)->assertOk()->assertJsonCount(1, 'data');

        // Validation errors come back as JSON.
        $this->postJson('/api/v1/invoices', ['type' => 'sale', 'party_id' => $c->id, 'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $p->id, 'unit_id' => $p->unit_id, 'quantity' => 999, 'price' => 100]]], $h)->assertStatus(422)->assertJsonValidationErrors('items');

        $this->postJson('/api/v1/auth/logout', [], $h)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me', $h)->assertUnauthorized();
    }

    public function test_tokens_respect_permissions_and_tenancy(): void
    {
        $this->setUpBusiness('wholesale');
        $storekeeper = User::create(['name' => 'S', 'email' => 's@t.pk', 'password' => 'secret123', 'branch_id' => $this->owner->branch_id]);
        $storekeeper->assignRole('Storekeeper'); // no api.use
        $cashier = User::create(['name' => 'C', 'email' => 'c@t.pk', 'password' => 'secret123', 'branch_id' => $this->owner->branch_id]);
        $cashier->assignRole('Cashier');
        $mine = $this->product();
        $other = app(BusinessProvisioner::class)->create(['name' => 'Other'], ['name' => 'O', 'email' => 'o@o.pk', 'password' => 'secret123']);
        $foreign = Tenant::run($other->business_id, fn () => $this->product(['name' => 'Foreign item']));
        auth()->logout();
        Tenant::forget();

        $this->postJson('/api/v1/auth/login', ['email' => 's@t.pk', 'password' => 'secret123', 'device_name' => 'x'])->assertStatus(422);

        $h = ['Authorization' => 'Bearer '.$this->token('c@t.pk')];
        $this->getJson("/api/v1/products/{$mine->id}", $h)->assertOk();
        $this->getJson("/api/v1/products/{$foreign->id}", $h)->assertNotFound();
        $this->getJson('/api/v1/dashboard', $h)->assertForbidden();
        $this->getJson('/api/v1/invoices?type=purchase', $h)->assertForbidden();
    }
}
