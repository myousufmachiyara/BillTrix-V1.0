<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use Auditable, BelongsToBusiness;

    public const TYPES = ['quotation' => 'Quotation', 'sale_order' => 'Sales Order', 'purchase_order' => 'Purchase Order'];

    public const STATUSES = ['open' => 'Open', 'partial' => 'Partly fulfilled', 'completed' => 'Completed', 'converted' => 'Converted', 'cancelled' => 'Cancelled'];

    protected $attributes = ['status' => 'open'];

    protected $fillable = ['branch_id', 'warehouse_id', 'type', 'number', 'date', 'due_date', 'party_id', 'quotation_id', 'status', 'reference',
        'subtotal', 'item_discount', 'discount', 'tax_total', 'other_charges', 'total', 'notes', 'terms', 'created_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date', 'due_date' => 'date',
            'subtotal' => 'decimal:2', 'item_discount' => 'decimal:2', 'discount' => 'decimal:2', 'tax_total' => 'decimal:2',
            'other_charges' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'quotation_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function getTitleAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'partial'], true);
    }

    public function isSaleSide(): bool
    {
        return $this->type !== 'purchase_order';
    }
}
