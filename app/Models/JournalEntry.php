<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use Auditable, BelongsToBusiness;

    public const VOUCHER_TYPES = ['receipt' => 'Receipt', 'payment' => 'Payment', 'journal' => 'Journal', 'contra' => 'Contra (Cash/Bank Transfer)'];

    protected $fillable = ['branch_id', 'number', 'type', 'date', 'source_type', 'source_id', 'party_id', 'reference', 'narration', 'amount', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'wht_rate' => 'decimal:3', 'wht_amount' => 'decimal:2'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isVoucher(): bool
    {
        return array_key_exists($this->type, self::VOUCHER_TYPES) && $this->source_type === null;
    }
}
