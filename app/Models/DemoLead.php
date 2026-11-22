<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A visitor who opened the public demo. Deliberately not scoped to a business. */
class DemoLead extends Model
{
    protected $fillable = ['name', 'phone', 'business_name', 'city', 'business_type', 'source', 'campaign', 'ip', 'user_agent',
        'visits', 'status', 'notes', 'last_seen_at'];

    protected function casts(): array
    {
        return ['visits' => 'integer', 'last_seen_at' => 'datetime'];
    }

    /**
     * A Pakistani mobile in any common form (0330-6505059, +92 330 6505059, 923306505059) becomes 03306505059;
     * other international numbers keep a leading +. Anything else is null.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        $digits = preg_replace('/\D+/', '', $raw);
        if (preg_match('/^(?:0092|92|0)?(3\d{9})$/', $digits, $m)) {
            return self::looksFake('0'.$m[1]) ? null : '0'.$m[1];
        }
        if (str_starts_with($raw, '+') && strlen($digits) >= 10 && strlen($digits) <= 15) {
            return '+'.$digits;
        }

        return null;
    }

    /** Numbers people type to get past the form: 0300 0000000, 0333 3333333, 0300 1234567 and the like. */
    public static function looksFake(string $phone): bool
    {
        $last7 = substr($phone, -7);

        return (bool) preg_match('/(\d)\1{6}/', $phone)                       // seven of the same digit in a row
            || in_array($last7, ['1234567', '7654321', '0123456', '9876543'], true)
            || count(array_unique(str_split(substr($phone, 4)))) <= 2;          // e.g. 0300 1212121
    }

    /** wa.me link: Pakistani numbers in international form. */
    public function whatsappUrl(): string
    {
        $digits = preg_replace('/\D+/', '', $this->phone);

        return 'https://wa.me/'.(str_starts_with($this->phone, '0') ? '92'.substr($digits, 1) : $digits);
    }

    public function scopeSearch(Builder $q, ?string $term): void
    {
        if (! $term = trim((string) $term)) {
            return;
        }
        $digits = preg_replace('/\D+/', '', $term);
        $q->where(function (Builder $w) use ($term, $digits) {
            $w->where('name', 'like', "%{$term}%")->orWhere('business_name', 'like', "%{$term}%")->orWhere('city', 'like', "%{$term}%");
            if (strlen($digits) >= 4) {
                $w->orWhere('phone', 'like', '%'.substr($digits, -9).'%');
            }
        });
    }
}
