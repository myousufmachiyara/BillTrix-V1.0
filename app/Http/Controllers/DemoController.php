<?php

namespace App\Http\Controllers;

use App\Models\DemoLead;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The public demo: a visitor leaves their details and is signed in to the shared demo business. */
class DemoController extends Controller
{
    /** /demo?utm_source=facebook&utm_campaign=… — remember where the visitor came from, then show the form. */
    public function enter(Request $request): RedirectResponse
    {
        abort_unless(config('billtrix.demo.enabled'), 404);
        self::rememberSource($request);

        return redirect()->route('login');
    }

    public function start(Request $request): RedirectResponse
    {
        abort_unless(config('billtrix.demo.enabled'), 404);

        // Bots fill every field, people never see this one.
        if ($request->filled('website')) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:25'],
            'business_name' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:60'],
            'business_type' => ['nullable', Rule::in(config('billtrix.demo.business_types'))],
        ]);

        $phone = DemoLead::normalizePhone($data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => __('Enter a valid mobile number, e.g. 0300 1234567.')]);
        }

        $demoUser = User::withoutGlobalScopes()->where('username', config('billtrix.demo.username'))->where('is_active', true)->first();
        if (! $demoUser) {
            Log::warning('Demo sign-in failed: no active demo user. Run php artisan billtrix:demo-reset.');

            return back()->withInput()->withErrors(['phone' => __('The demo is being prepared. Please try again in a few minutes.')]);
        }

        $details = array_filter([
            'name' => trim($data['name']),
            'business_name' => trim((string) ($data['business_name'] ?? '')),
            'city' => trim((string) ($data['city'] ?? '')),
            'business_type' => $data['business_type'] ?? null,
        ], fn ($v) => $v !== '' && $v !== null);
        $seen = ['ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'last_seen_at' => now()];

        $lead = DemoLead::where('phone', $phone)->first();
        if ($lead) {
            $lead->fill($details + $seen);
            $lead->visits++;
            $lead->campaign ??= session('demo_campaign');
            $lead->source ??= session('demo_source');
            $lead->save();
        } else {
            $lead = DemoLead::create($details + $seen + [
                'phone' => $phone,
                'source' => session('demo_source') ?? 'direct',
                'campaign' => session('demo_campaign'),
            ]);
        }

        Auth::login($demoUser);
        $request->session()->regenerate();
        session(['demo_lead_id' => $lead->id]);

        return redirect()->route('dashboard')->with('success', __('Welcome, :name! Try a sale, a purchase or a report — everything here is sample data.', ['name' => trim($data['name'])]));
    }

    /** Ad and link parameters first; otherwise the site that sent the visitor (facebook.com, billtrix.com…). */
    public static function rememberSource(Request $request): void
    {
        $source = $request->query('utm_source', $request->query('source', $request->query('ref')));
        if (is_string($source) && $source !== '') {
            session(['demo_source' => mb_substr($source, 0, 80)]);
            if (is_string($c = $request->query('utm_campaign')) && $c !== '') {
                session(['demo_campaign' => mb_substr($c, 0, 120)]);
            }

            return;
        }
        if (! session()->has('demo_source') && ($ref = $request->headers->get('referer'))) {
            $host = parse_url($ref, PHP_URL_HOST);
            if ($host && $host !== $request->getHost()) {
                session(['demo_source' => mb_substr(preg_replace('/^(www\.|l\.|lm\.|m\.)/', '', $host), 0, 80)]);
            }
        }
    }
}
