<?php

namespace Tests\Feature;

use App\Console\Commands\DemoReset;
use App\Models\DemoLead;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private User $visitor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['billtrix.demo.enabled' => true, 'billtrix.demo.whatsapp' => '0330 6505059']);
        $this->setUpBusiness('wholesale', ['pos' => true]);
        $this->visitor = DemoReset::createVisitor($this->owner->business);
        Tenant::forget();
        auth()->logout();
    }

    private function form(array $over = []): array
    {
        return array_merge(['name' => 'Usman', 'phone' => '0330-6505059', 'business_name' => 'Usman Garments', 'city' => 'Karachi',
            'business_type' => 'Garments & shoes'], $over);
    }

    public function test_phone_numbers_are_normalised(): void
    {
        foreach (['03306505059', '0330-6505059', '0330 650 5059', '+92 330 6505059', '923306505059', '00923306505059'] as $raw) {
            $this->assertSame('03306505059', DemoLead::normalizePhone($raw), $raw);
        }
        $this->assertSame('+971501234567', DemoLead::normalizePhone('+971 50 123 4567'));
        foreach (['12345', '0213456789', 'abc', '', '0430 6505059'] as $bad) {
            $this->assertNull(DemoLead::normalizePhone($bad), $bad);
        }
    }

    public function test_login_page_shows_the_demo_form_only_in_demo_mode(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('demo.start'), false)->assertDontSee('name="password"', false)->assertDontSee('12345678');
        $this->get('/admin')->assertOk()->assertSee('name="password"', false)->assertDontSee(route('demo.start'), false);

        // A wrong staff password goes back to /admin, not to the visitor form.
        $this->post('/login', ['login' => 'nobody', 'password' => 'wrong'])->assertRedirect(route('admin.login'))->assertSessionHasErrors('login');

        config(['billtrix.demo.enabled' => false]);
        $this->get('/login')->assertOk()->assertDontSee(route('demo.start'), false);
        $this->get('/demo')->assertNotFound();
        $this->post('/demo/start', $this->form())->assertNotFound();
    }

    public function test_a_visitor_becomes_a_lead_and_is_signed_in(): void
    {
        $this->get('/demo?utm_source=facebook&utm_campaign=nov-launch')->assertRedirect(route('login'));
        $this->post('/demo/start', $this->form())->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($this->visitor);
        $lead = DemoLead::sole();
        $this->assertSame('03306505059', $lead->phone);
        $this->assertSame(['Usman', 'Usman Garments', 'Karachi', 'Garments & shoes', 'facebook', 'nov-launch', 1, 'new'],
            [$lead->name, $lead->business_name, $lead->city, $lead->business_type, $lead->source, $lead->campaign, $lead->visits, $lead->status]);
        $this->assertSame('https://wa.me/923306505059', $lead->whatsappUrl());

        $this->get('/')->assertOk()->assertSee('You are exploring the BillTrix demo')->assertSee('https://wa.me/923306505059', false);

        // Same person again, number written differently: one lead, two visits, first source kept.
        auth()->logout();
        $this->flushSession();
        $this->get('/login', ['referer' => 'https://www.billtrix.com/']);
        $this->post('/demo/start', $this->form(['phone' => '+92 330 6505059', 'city' => 'Lahore']))->assertRedirect(route('dashboard'));
        $lead->refresh();
        $this->assertSame([1, 2, 'Lahore', 'facebook'], [DemoLead::count(), $lead->visits, $lead->city, $lead->source]);
    }

    public function test_a_new_number_is_asked_for_details_and_a_returning_one_goes_straight_in(): void
    {
        // Step 1, new number: no lead yet, the details form appears with the number kept.
        $this->from('/login')->post('/demo/start', ['phone' => '0330 6505059'])->assertRedirect('/login')->assertSessionHas('demo_step', 'details');
        $this->assertGuest();
        $this->assertSame(0, DemoLead::count());
        $this->withSession(['demo_step' => 'details', '_old_input' => ['phone' => '0330 6505059']])->get('/login')
            ->assertSee('id="demo-name"', false)->assertSee('0330 6505059');

        // Step 2: details given, signed in.
        $this->post('/demo/start', $this->form())->assertRedirect(route('dashboard'))->assertCookie('demo_phone');
        auth()->logout();
        $this->flushSession();

        // Next visit: the number alone is enough, and nothing they told us is lost.
        $this->get('/login')->assertDontSee('id="demo-name"', false);
        $this->post('/demo/start', ['phone' => '+92 330 6505059'])->assertRedirect(route('dashboard'))->assertSessionHas('success', fn ($m) => str_contains($m, 'Welcome back'));
        $this->assertAuthenticatedAs($this->visitor);
        $lead = DemoLead::sole();
        $this->assertSame(['Usman', 'Usman Garments', 'Karachi', 2], [$lead->name, $lead->business_name, $lead->city, $lead->visits]);
    }

    public function test_the_referring_site_is_the_source_when_there_is_no_tag(): void
    {
        $this->get('/login', ['referer' => 'https://www.billtrix.com/features']);
        $this->post('/demo/start', $this->form());
        $this->assertSame('billtrix.com', DemoLead::sole()->source);
    }

    public function test_bad_input_and_bots_create_no_lead(): void
    {
        $this->post('/demo/start', $this->form(['phone' => '12345']))->assertSessionHasErrors('phone');
        $this->post('/demo/start', $this->form(['name' => '']))->assertSessionHas('demo_step', 'details');
        $this->post('/demo/start', $this->form(['business_type' => 'Spaceships']))->assertSessionHasErrors('business_type');
        $this->post('/demo/start', $this->form(['website' => 'http://spam.example']))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertSame(0, DemoLead::count());
    }

    public function test_the_visitor_can_use_the_app_but_not_settings_or_leads(): void
    {
        $this->post('/demo/start', $this->form());
        foreach (['/sales/create', '/pos', '/products', '/reports', '/purchases/create'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (['/settings/business', '/settings/users', '/settings/roles', '/demo-leads', '/demo-leads/export', '/settings/backup', '/settings/audit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->put('/my-password', ['current_password' => 'x', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertForbidden();
        $this->get('/')->assertDontSee(route('demo-leads.index'), false);
    }

    public function test_the_owner_follows_up_leads(): void
    {
        $this->post('/demo/start', $this->form());
        auth()->logout();
        $this->post('/demo/start', $this->form(['name' => 'Ali', 'business_name' => 'Ali Hardware', 'phone' => '0321 1112223', 'city' => 'Lahore', 'business_type' => 'Other']));
        auth()->logout();
        $this->actingAs($this->owner);

        $this->get('/')->assertSee(route('demo-leads.index'), false);
        $this->get('/demo-leads')->assertOk()->assertSee('Usman Garments')->assertSee('https://wa.me/923211112223', false);
        $this->get('/demo-leads?q=Lahore')->assertOk()->assertSee('Ali Hardware')->assertDontSee('Usman Garments');

        $lead = DemoLead::where('phone', '03211112223')->sole();
        $this->put("/demo-leads/{$lead->id}", ['status' => 'contacted', 'notes' => 'Call back Monday'])->assertRedirect();
        $this->assertSame(['contacted', 'Call back Monday'], [$lead->fresh()->status, $lead->fresh()->notes]);
        $this->put("/demo-leads/{$lead->id}", ['status' => 'bogus'])->assertSessionHasErrors('status');
        $this->get('/demo-leads?status=contacted')->assertSee('Ali Hardware')->assertDontSee('Usman Garments');

        $this->get('/demo-leads/export')->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        config(['billtrix.demo.enabled' => false]);
        $this->get('/demo-leads')->assertForbidden();
    }

    public function test_reset_refuses_outside_demo_mode_or_without_an_owner_password(): void
    {
        config(['billtrix.demo.enabled' => false]);
        $this->artisan('billtrix:demo-reset', ['--force' => true])->assertFailed();
        config(['billtrix.demo.enabled' => true, 'billtrix.demo.admin_password' => 'short']);
        $this->artisan('billtrix:demo-reset', ['--force' => true])->assertFailed();
        $this->assertSame(1, \App\Models\Business::count());
    }
}
