<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Invoices and orders share one shape. @mixin \App\Models\Invoice|\App\Models\Order */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isInvoice = $this->resource instanceof Invoice;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'number' => $this->number,
            'date' => $this->date->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'party' => $this->whenLoaded('party', fn () => ['id' => $this->party->id, 'name' => $this->party->name]),
            'warehouse_id' => $this->warehouse_id,
            'reference' => $this->reference,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'item_discount' => (float) $this->item_discount,
            'discount' => (float) $this->discount,
            'tax_total' => (float) $this->tax_total,
            'other_charges' => (float) $this->other_charges,
            'wht_amount' => $this->when($isInvoice, fn () => (float) $this->wht_amount),
            'service_fee' => $this->when($isInvoice, fn () => (float) $this->service_fee),
            'total' => (float) $this->total,
            'paid_amount' => $this->when($isInvoice, fn () => (float) $this->paid_amount),
            'balance' => $this->when($isInvoice, fn () => $this->balance),
            'fbr' => $this->when($isInvoice && $this->fbr_status, fn () => ['status' => $this->fbr_status, 'invoice_number' => $this->fbr_invoice_number]),
            'print_url' => $isInvoice ? route('invoices.print', [collect(\App\Http\Controllers\Trade\InvoiceController::DOCS)->search(fn ($d) => $d[0] === $this->type), $this->id]) : null,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'product_id' => $i->product_id,
                'name' => $i->relationLoaded('product') ? $i->itemName() : null,
                'variant_id' => $i->variant_id,
                'unit_id' => $i->unit_id,
                'quantity' => (float) $i->quantity,
                'price' => (float) $i->price,
                'discount_percent' => (float) $i->discount_percent,
                'tax_rate' => (float) $i->tax_rate,
                'tax_amount' => (float) $i->tax_amount,
                'amount' => (float) $i->amount,
                'pending_quantity' => $isInvoice ? null : $i->pending,
            ])),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
