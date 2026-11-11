@php
    [$type, $perm, $partyType, $label] = $meta;
    $editing = $order->exists;
    $title = ($editing ? 'Edit ' : 'New ').\App\Models\Order::TYPES[$type].($editing ? ' '.$order->number : '');
@endphp
@extends('layouts.app')
@section('title', $title)
@section('header-actions')
    <a href="{{ route('orders.index', $doc) }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> {{ $label }}</a>
@endsection

@section('content')
<form method="POST" action="{{ $editing ? route('orders.update', [$doc, $order]) : route('orders.store', $doc) }}" id="invoice-form" autocomplete="off">
    @csrf @if ($editing) @method('PUT') @endif
    @if ($order->quotation_id)<input type="hidden" name="quotation_id" value="{{ $order->quotation_id }}">@endif
    <div class="row">
        <div class="col-xl-9">
            <x-card>
                <div class="row g-2">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">{{ ucfirst($partyType) }} <span class="text-danger">*</span></label>
                        <x-party-select :type="$partyType" :party="$order->party" required />
                    </div>
                    <div class="col-md-2 col-6 mb-2">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="{{ old('date', $order->date?->toDateString()) }}" required>
                    </div>
                    <div class="col-md-2 col-6 mb-2">
                        <label class="form-label">{{ $type === 'quotation' ? 'Valid until' : 'Delivery by' }}</label>
                        <input type="date" name="due_date" class="form-control" value="{{ old('due_date', $order->due_date?->toDateString()) }}">
                    </div>
                    <div class="col-md-2 mb-2 {{ $warehouses->count() > 1 ? '' : 'd-none' }}">
                        <label class="form-label">{{ $type === 'purchase_order' ? 'Receive into' : 'Ship from' }}</label>
                        <select name="warehouse_id" class="form-select" required>
                            @foreach ($warehouses as $id => $name)<option value="{{ $id }}" @selected((int) old('warehouse_id', $order->warehouse_id) === $id)>{{ $name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="form-label">{{ $type === 'purchase_order' ? 'Supplier ref.' : 'Customer PO / ref.' }}</label>
                        <input type="text" name="reference" class="form-control" value="{{ old('reference', $order->reference) }}" maxlength="100">
                    </div>
                    @if ($order->quotation_id)
                        <div class="col-12"><div class="alert alert-info py-2 mb-0">Created from quotation — it will be marked as converted when you save.</div></div>
                    @endif
                </div>
            </x-card>

            <x-card :body="false">
                <div class="card-body pb-2">
                    <div class="row g-2">
                        <div class="col-md-5">
                            <div class="input-group"><span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                <input type="text" id="barcode" class="form-control" placeholder="Scan barcode or SKU, Enter"></div>
                        </div>
                        <div class="col-md-7"><select id="product-search" data-placeholder="Search product by name…  (F3)"></select></div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="lines">
                        <thead class="table-light"><tr>
                            <th>Item</th>
                            <th @class(['d-none' => ! $business->feature('multi_unit')]) style="width:110px">Unit</th>
                            <th class="num" style="width:100px">Qty</th><th class="num" style="width:120px">Rate</th><th class="num" style="width:80px">Disc %</th>
                            <th @class(['num', 'd-none' => ! $business->feature('tax')]) style="width:80px">Tax %</th>
                            <th class="num">Amount</th><th style="width:30px"></th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="card-body pt-2 small text-muted"><span data-t="count">0</span> item(s)</div>
            </x-card>

            <x-card>
                <div class="row">
                    <x-input name="notes" label="Notes" type="textarea" :value="$order->notes" col="col-md-6" />
                    @if ($type !== 'purchase_order')
                        <x-input name="terms" label="Terms & conditions" type="textarea" :value="$order->terms" col="col-md-6" rows="3" />
                    @endif
                </div>
            </x-card>
        </div>

        <div class="col-xl-3">
            <x-card title="Totals">
                <table class="table table-borderless totals-table mb-2">
                    <tr><td>Gross</td><td class="num" data-t="subtotal">0.00</td></tr>
                    <tr><td>Item discounts</td><td class="num" data-t="item_discount">0.00</td></tr>
                    @feature('tax')<tr><td>Tax</td><td class="num" data-t="tax">0.00</td></tr>@endfeature
                    <tr><td>Discount</td><td><input type="number" step="any" min="0" name="discount" class="form-control form-control-sm text-end" value="{{ old('discount', (float) $order->discount) }}"></td></tr>
                    <tr><td>{{ $type === 'purchase_order' ? 'Freight / other' : 'Delivery / other' }}</td><td><input type="number" step="any" min="0" name="other_charges" class="form-control form-control-sm text-end" value="{{ old('other_charges', (float) $order->other_charges) }}"></td></tr>
                    <tr class="grand"><td>Total</td><td class="num" data-t="total">0.00</td></tr>
                </table>
                <p class="small text-muted mb-3">{{ $type === 'quotation' ? 'A quotation does not reserve stock or affect your accounts.' : 'An order does not move stock or post to accounts until goods are delivered/received or invoiced.' }}</p>
                <div class="d-grid"><button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save</button></div>
            </x-card>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/invoice.js') }}"></script>
<script>
    BT.invoiceEditor({
        form: '#invoice-form',
        type: @js($type === 'purchase_order' ? 'purchase' : 'sale'),
        lines: @js($lines),
        returnOf: false,
        tax: @js($business->feature('tax')),
        inclusive: @js($business->feature('prices_include_tax')),
        multiUnit: @js($business->feature('multi_unit')),
        creditLimits: false,
    });
</script>
@endpush
