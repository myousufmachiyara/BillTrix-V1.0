<?php

namespace Tests\Feature;

use App\Models\DemoLead;
use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBusiness('wholesale');
        $this->owner->forceFill(['username' => 'owner1', 'password' => 'Right#Pass1'])->save();
        auth()->logout();
    }

    public function test_an_account_locks_after_five_wrong_passwords(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'owner1', 'password' => 'wrong'.$i])->assertSessionHasErrors('login');
        }
        // Even the right password is refused while locked.
        $this->post('/login', ['login' => 'owner1', 'password' => 'Right#Pass1'])
            ->assertSessionHasErrors(['login' => __('Too many sign-in attempts. Try again in :minutes minute(s).', ['minutes' => 15])]);
        $this->assertGuest();

        // 15 minutes later it works again, and the counter starts over.
        $this->travel(16)->minutes();
        $this->post('/login', ['login' => 'owner1', 'password' => 'Right#Pass1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_an_attack_from_many_ips_locks_the_account(): void
    {
        for ($i = 0; $i < 20; $i++) {
            // 4 tries from each of 5 addresses: never enough to lock one address, enough to lock the account.
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.intdiv($i, 4)])
                ->post('/login', ['login' => 'OWNER1', 'password' => 'guess'.$i]);
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->post('/login', ['login' => 'owner1', 'password' => 'Right#Pass1'])->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_the_mobile_api_login_is_locked_too(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['login' => 'owner1', 'password' => 'x', 'device_name' => 'p'])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/login', ['login' => 'owner1', 'password' => 'Right#Pass1', 'device_name' => 'p'])->assertStatus(429);
    }

    public function test_sql_injection_attempts_do_nothing(): void
    {
        foreach (["' OR '1'='1", "admin'--", "owner1' OR 1=1 #", '" OR ""="'] as $payload) {
            $this->post('/login', ['login' => $payload, 'password' => $payload]);
            $this->assertGuest();
        }
        $this->actingAs($this->owner);
        $this->get('/parties?type=customer&q='.urlencode("x' OR 1=1; DROP TABLE parties; --"))->assertOk();
        $this->get('/products?q='.urlencode("%' UNION SELECT password FROM users --"))->assertOk()->assertDontSee('$2y$', false);
        $this->assertGreaterThan(0, Party::count());
    }

    public function test_scripts_typed_into_forms_are_shown_as_text(): void
    {
        config(['billtrix.demo.enabled' => true]);
        DemoLead::create(['name' => '<script>alert(1)</script>', 'phone' => '03001234567', 'last_seen_at' => now()]);
        $this->actingAs($this->owner);
        $this->get('/demo-leads')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_pages_send_security_headers(): void
    {
        $this->get('/login')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }

    public function test_signed_out_visitors_reach_nothing(): void
    {
        foreach (['/', '/sales', '/products', '/reports', '/settings/users', '/settings/backup/download', '/demo-leads', '/api/v1/me'] as $url) {
            $this->assertContains($this->get($url)->status(), [302, 401, 404], $url);
        }
    }
}
