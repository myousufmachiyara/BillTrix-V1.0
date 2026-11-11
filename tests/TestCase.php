<?php

namespace Tests;

use App\Models\Party;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BusinessProvisioner;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected User $owner;

    protected Warehouse $warehouse;

    protected function tearDown(): void
    {
        Tenant::forget();
        \App\Models\Account::flushSystemCache();
        parent::tearDown();
    }

    protected function setUpBusiness(string $category = 'wholesale', array $settings = []): User
    {
        $this->owner = app(BusinessProvisioner::class)->create(
            ['name' => 'Test Traders', 'category' => $category],
            ['name' => 'Owner', 'email' => uniqid().'@test.pk', 'password' => 'secret123'],
        );
        Tenant::set($this->owner->business);
        if ($settings) {
            $this->owner->business->update(['settings' => $settings]);
            Tenant::set($this->owner->business->fresh());
        }
        $this->actingAs($this->owner);
        $this->warehouse = Warehouse::firstOrFail();

        return $this->owner;
    }

    protected function product(array $attrs = []): Product
    {
        return Product::create($attrs + [
            'name' => 'Item '.uniqid(),
            'unit_id' => Unit::where('short_name', 'pcs')->value('id'),
            'sale_price' => 100,
            'purchase_price' => 60,
        ]);
    }

    protected function party(string $type = 'customer', array $attrs = []): Party
    {
        return Party::create($attrs + ['type' => $type, 'name' => ucfirst($type).' '.uniqid()]);
    }
}
