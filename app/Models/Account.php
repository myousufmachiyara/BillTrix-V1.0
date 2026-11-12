<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Account extends Model
{
    use Auditable, BelongsToBusiness;

    public const TYPES = ['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expense'];

    /** Types whose normal balance is a debit. */
    public const DEBIT_TYPES = ['asset', 'expense'];

    protected $attributes = ['is_active' => true, 'is_group' => false];

    protected $fillable = ['parent_id', 'code', 'name', 'type', 'subtype', 'system_key', 'is_group', 'is_active', 'description'];

    protected function casts(): array
    {
        return ['is_group' => 'boolean', 'is_active' => 'boolean'];
    }

    private static array $systemCache = [];

    /** Resolve a posting account by its stable system key (e.g. 'sales', 'inventory'). */
    public static function system(string $key): self
    {
        $cacheKey = Tenant::idOrFail().':'.$key;

        return self::$systemCache[$cacheKey] ??= static::where('system_key', $key)->first()
            ?? throw new RuntimeException("System account [{$key}] is missing from the chart of accounts.");
    }

    public static function flushSystemCache(): void
    {
        self::$systemCache = [];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function scopePostable(Builder $q): void
    {
        $q->where('is_group', false)->where('is_active', true);
    }

    public function scopeCashOrBank(Builder $q): void
    {
        $q->postable()->whereIn('subtype', ['cash', 'bank']);
    }

    public function isDebitNature(): bool
    {
        return in_array($this->type, self::DEBIT_TYPES, true);
    }

    public function isControlAccount(): bool
    {
        return in_array($this->subtype, ['receivable', 'payable'], true);
    }

    public function getLabelAttribute(): string
    {
        return "{$this->code} — {$this->name}";
    }
}
