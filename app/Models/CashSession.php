<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    use BelongsToBusiness;

    /** Pakistani rupee notes and coins for the closing count. */
    public const DENOMINATIONS = [5000, 1000, 500, 100, 50, 20, 10, 5, 2, 1];

    protected $fillable = ['branch_id', 'user_id', 'account_id', 'number', 'opened_at', 'closed_at', 'opening_float', 'expected_cash',
        'counted_cash', 'difference', 'denominations', 'notes', 'closed_by'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime', 'closed_at' => 'datetime', 'opening_float' => 'decimal:2', 'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2', 'difference' => 'decimal:2', 'denominations' => 'array',
        ];
    }

    public function scopeOpen(Builder $q): void
    {
        $q->whereNull('closed_at');
    }

    public static function currentFor(User $user): ?self
    {
        return static::open()->where('user_id', $user->id)->latest('id')->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }
}
