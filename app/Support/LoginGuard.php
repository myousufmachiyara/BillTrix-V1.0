<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Brute-force protection for sign-in (web and mobile API).
 *  - 5 wrong passwords for one account from one IP  → that IP is locked out of that account for 15 minutes.
 *  - 20 wrong passwords for one account from any IPs → the account is locked for 1 hour (stops attacks spread over many IPs).
 * A correct password clears the counters. Every lock is written to the log.
 */
class LoginGuard
{
    public const PER_IP = 5;

    public const PER_IP_MINUTES = 15;

    public const PER_ACCOUNT = 20;

    public const PER_ACCOUNT_MINUTES = 60;

    /** Throws if this account is locked for this request. */
    public static function check(Request $request, string $login, string $field = 'login'): void
    {
        foreach ([[self::ipKey($request, $login), self::PER_IP], [self::accountKey($login), self::PER_ACCOUNT]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                throw ValidationException::withMessages([
                    $field => __('Too many sign-in attempts. Try again in :minutes minute(s).', ['minutes' => max(1, $minutes)]),
                ])->status(429);
            }
        }
    }

    public static function failed(Request $request, string $login): void
    {
        RateLimiter::hit(self::ipKey($request, $login), self::PER_IP_MINUTES * 60);
        RateLimiter::hit(self::accountKey($login), self::PER_ACCOUNT_MINUTES * 60);

        Log::notice('Failed sign-in', ['login' => Str::limit($login, 60), 'ip' => $request->ip()]);
        if (RateLimiter::attempts(self::ipKey($request, $login)) === self::PER_IP || RateLimiter::attempts(self::accountKey($login)) === self::PER_ACCOUNT) {
            Log::warning('Sign-in locked after repeated wrong passwords', ['login' => Str::limit($login, 60), 'ip' => $request->ip()]);
        }
    }

    public static function succeeded(Request $request, string $login): void
    {
        RateLimiter::clear(self::ipKey($request, $login));
        RateLimiter::clear(self::accountKey($login));
    }

    private static function ipKey(Request $request, string $login): string
    {
        return 'login-ip:'.sha1(Str::lower(trim($login)).'|'.$request->ip());
    }

    private static function accountKey(string $login): string
    {
        return 'login-account:'.sha1(Str::lower(trim($login)));
    }
}
