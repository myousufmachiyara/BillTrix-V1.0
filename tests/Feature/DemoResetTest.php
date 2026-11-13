<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\DemoLead;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Runs the real nightly reset (no RefreshDatabase: the command rebuilds the database itself). */
class DemoResetTest extends TestCase
{
    public function test_reset_reloads_the_demo_and_keeps_the_leads(): void
    {
        config(['billtrix.demo.enabled' => true, 'billtrix.demo.admin_password' => 'MySecret#2026']);
        Artisan::call('migrate:fresh', ['--force' => true]);
        DemoLead::create(['name' => 'Usman', 'phone' => '03306505059', 'status' => 'contacted', 'notes' => 'Keen', 'last_seen_at' => now()]);

        $this->artisan('billtrix:demo-reset', ['--force' => true])->assertSuccessful();
        // A visitor's mess is gone the next night, the lead is not.
        $this->artisan('billtrix:demo-reset', ['--force' => true])->assertSuccessful();

        $this->assertSame(['Usman', 'contacted', 'Keen'], [DemoLead::sole()->name, DemoLead::sole()->status, DemoLead::sole()->notes]);
        $this->assertSame(1, Business::count());

        $owner = User::withoutGlobalScopes()->where('username', 'admin')->sole();
        $this->assertTrue(Hash::check('MySecret#2026', $owner->password));
        $this->assertFalse(Hash::check('password', $owner->password), 'the seeder default password must not survive');

        $visitor = User::withoutGlobalScopes()->where('username', 'demo')->sole();
        $business = Business::sole();
        $this->assertTrue($business->setting('variants') && $business->setting('quotations'));
        Tenant::run($business, function () use ($visitor) {
            $this->assertTrue($visitor->hasRole('Demo'));
            $this->assertTrue($visitor->can('sales.create'));
            $this->assertFalse($visitor->can('settings.manage') || $visitor->can('users.view') || $visitor->can('fbr.manage'));
            $this->assertGreaterThan(10, Invoice::count());
        });

        // The visitor gets in through the form, the owner through the normal login.
        auth()->logout();   // the seeder signs its owner in while it posts transactions
        Tenant::forget();
        $this->post('/demo/start', ['name' => 'Ali', 'phone' => '03211112223'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($visitor);
        auth()->logout();
        $this->flushSession();
        $this->get('/admin')->assertOk();
        $this->post('/login', ['login' => 'admin', 'password' => 'MySecret#2026'])->assertRedirect(route('dashboard'));
        $this->get('/demo-leads')->assertOk()->assertSee('Ali')->assertSee('Usman');
        Tenant::forget();
    }
}
