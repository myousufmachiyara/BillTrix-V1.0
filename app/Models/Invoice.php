<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    use BelongsToBusiness;

    public const TYPES = [
        'sale' => 'Sale Invoice', 'purchase' => 'Purchase Invoice',
        'sale_return' => 'Sale Return', 'purchase_return' => 'Purchase Return',
    ];

    protected $fillable = ['branch_id', 'warehouse_id', 'type', 'number', 'date', 'due_date', 'party_id', 'return_of_id', 'order_id', 'cash_session_id', 'payment_mode',
        'reference', 'customer_name', 'customer_phone', 'subtotal', 'item_discount', 'discount', 'tax_total', 'other_charges',
        'round_off', 'total', 'paid_amount', 'cost_total', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'due_date' => 'date',
            'subtotal' => 'decimal:2', 'item_discount' => 'decimal:2', 'discount' => 'decimal:2', 'tax_total' => 'decimal:2',
            'other_charges' => 'decimal:2', 'round_off' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2',
            'cost_total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function returnOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'return_of_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'return_of_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public function scopeOfType(Builder $q, string $type): void
    {
        $q->where('type', $type);
    }

    public function scopeOutstanding(Builder $q): void
    {
        $q->whereColumn('paid_amount', '<', 'total');
    }

    public function isSaleSide(): bool
    {
        return in_array($this->type, ['sale', 'sale_return'], true);
    }

    public function isReturn(): bool
    {
        return str_ends_with($this->type, '_return');
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->total - (float) $this->paid_amount, 2);
    }

    public function getStatusAttribute(): string
    {
        return match (true) {
            $this->balance <= 0.004 => 'paid',
            (float) $this->paid_amount > 0 => 'partial',
            default => 'unpaid',
        };
    }

    public function getProfitAttribute(): float
    {
        $netSale = (float) $this->total - (float) $this->tax_total;

        return round($netSale - (float) $this->cost_total, 2);
    }

    public function getTitleAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
