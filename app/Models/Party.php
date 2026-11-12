<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    use Auditable, BelongsToBusiness;

    protected $attributes = ['is_filer' => true, 'wht_exempt' => false, 'is_active' => true, 'is_walk_in' => false, 'credit_limit' => 0, 'credit_days' => 0];

    protected $fillable = ['type', 'name', 'code', 'contact_person', 'phone', 'email', 'address', 'city', 'ntn', 'cnic', 'province', 'is_filer', 'wht_exempt',
        'credit_limit', 'credit_days', 'price_level_id', 'is_walk_in', 'is_active'];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'is_walk_in' => 'boolean', 'is_active' => 'boolean', 'is_filer' => 'boolean', 'wht_exempt' => 'boolean'];
    }

    public function scopeCustomers(Builder $q): void
    {
        $q->whereIn('type', ['customer', 'both']);
    }

    public function scopeSuppliers(Builder $q): void
    {
        $q->whereIn('type', ['supplier', 'both']);
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PartyPrice::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Net balance across AR and AP (debit minus credit).
     * Positive = the party owes us; negative = we owe the party.
     */
    public function balance(?string $asOf = null): float
    {
        $q = $this->lines();
        if ($asOf) {
            $q->whereDate('date', '<=', $asOf);
        }

        return round((float) $q->sum('debit') - (float) $q->sum('credit'), 2);
    }

    public function isCustomer(): bool
    {
        return in_array($this->type, ['customer', 'both'], true);
    }

    public function isSupplier(): bool
    {
        return in_array($this->type, ['supplier', 'both'], true);
    }
}
