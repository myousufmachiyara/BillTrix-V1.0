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
            return '0'.$m[1];
        }
        if (str_starts_with($raw, '+') && strlen($digits) >= 10 && strlen($digits) <= 15) {
            return '+'.$digits;
        }

        return null;
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
