@php
    [$type, $perm, $partyType, $label] = $meta;
    $isSale = in_array($type, ['sale', 'sale_return']);
    $isReturn = str_ends_with($type, '_return');
    $editing = $invoice->exists;
    $title = ($editing ? 'Edit ' : 'New ').\App\Models\Invoice::TYPES[$type].($editing ? ' '.$invoice->number : '');
    $mode = old('payment_mode', $invoice->payment_mode ?? 'credit');
    $paidValue = old('paid_amount', $editing ? $paidNow : 0);
@endphp
@extends('layouts.app')
@section('title', $title)
@section('header-actions')
    <a href="{{ route('invoices.index', $doc) }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> {{ $label }}</a>
@endsection

@section('content')
<form method="POST" action="{{ $editing ? route('invoices.update', [$doc, $invoice]) : route('invoices.store', $doc) }}" id="invoice-form" autocomplete="off">
    @csrf
    @if ($editing) @method('PUT') @endif
    @if ($invoice->return_of_id)<input type="hidden" name="return_of_id" value="{{ $invoice->return_of_id }}">@endif
    @if ($invoice->order_id)<input type="hidden" name="order_id" value="{{ $invoice->order_id }}">@endif
    @php $linked = $invoice->return_of_id || collect($lines)->contains(fn ($l) => ! empty($l['order_item_id']) || ! empty($l['delivery_item_id'])); @endphp

    <div class="row">
        <div class="col-xl-9">
            <x-card>
                <div class="row g-2">
                    <div class="col-md-4 mb-2">
                        <label class="form-label">{{ $isSale ? 'Customer' : 'Supplier' }} <span class="text-danger">*</span></label>
                        <div class="input-group flex-nowrap">
                            <x-party-select :type="$partyType" :party="$invoice->party" required :disabled="$linked" />
                            @if (! $linked)
                                @can('parties.create')
                                    <a href="{{ route('parties.create', ['type' => $partyType]) }}" target="_blank" class="btn btn-outline-secondary" title="Add {{ $partyType }}"><i class="fas fa-plus"></i></a>
                                @endcan
                            @endif
                        </div>
                        @if ($linked)<input type="hidden" name="party_id" value="{{ $invoice->party_id }}">@endif
                    </div>
                    <div class="col-md-2 col-6 mb-2">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-control" value="{{ old('date', $invoice->date?->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3 col-6 mb-2 {{ $warehouses->count() > 1 ? '' : 'd-none' }}">
                        <label class="form-label">Warehouse</label>
                        <select name="warehouse_id" class="form-select" required>
                            @foreach ($warehouses as $id => $name)
                                <option value="{{ $id }}" @selected((int) old('warehouse_id', $invoice->warehouse_id) === $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">{{ $isSale ? 'Customer PO / Ref.' : 'Supplier bill no.' }}</label>
                        <input type="text" name="reference" class="form-control" value="{{ old('reference', $invoice->reference) }}" maxlength="100">
                    </div>
                    @if ($type === 'sale')
                        <div class="col-md-4 mb-2 walkin-fields">
                            <label class="form-label">Name on bill <small class="text-muted">(walk-in)</small></label>
                            <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name', $invoice->customer_name) }}" maxlength="100">
                        </div>
                        <div class="col-md-3 mb-2 walkin-fields">
                            <label class="form-label">Phone</label>
                            <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $invoice->customer_phone) }}" maxlength="30">
                        </div>
                    @endif
                    @if (! $invoice->return_of_id && $linked)
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0">
                                @if (collect($lines)->contains(fn ($l) => ! empty($l['delivery_item_id'])))
                                    Billing delivery challan(s) <strong>{{ $invoice->reference }}</strong>. Quantities come from the challans; you can still set rates and discounts.
                                @else
                                    Billing against order <strong>{{ $invoice->relationLoaded('order') ? $invoice->order?->number : $invoice->reference }}</strong>. Lines show what is still pending; reduce or remove what you are not billing now.
                                @endif
                            </div>
                        </div>
                    @endif
                    @if ($invoice->return_of_id)
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0">
                                Returning items from <strong>{{ $invoice->returnOf?->number }}</strong>
                                ({{ $invoice->returnOf?->date?->format('d M Y') }}). Enter the quantity coming back for each line; lines left at 0 are ignored.
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card :body="false">
                @unless ($invoice->return_of_id)
                    <div class="card-body pb-2">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                    <input type="text" id="barcode" class="form-control scan-box" placeholder="Scan barcode or type SKU, Enter  (qty*code works)">
                                </div>
                            </div>
                            <div class="col-md-7">
                                <select id="product-search" data-placeholder="Search product by name…  (F3)"></select>
                            </div>
                        </div>
                    </div>
                @endunless
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="lines">
                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th @class(['d-none' => ! $business->feature('multi_unit')]) style="width:110px">Unit</th>
                                <th class="num" style="width:100px">Qty</th>
                                <th class="num" style="width:120px">Rate</th>
                                <th class="num" style="width:80px">Disc %</th>
                                <th @class(['num', 'd-none' => ! $business->feature('tax')]) style="width:80px">Tax %</th>
                                <th class="num">Amount</th>
                                <th style="width:30px"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="card-body pt-2 small text-muted">
                    <span data-t="count">0</span> item(s) ·
                    <span class="kbd">F2</span> scan · <span class="kbd">F3</span> search · <span class="kbd">F9</span> save
                </div>
            </x-card>

            <x-card>
                <x-input name="notes" label="Notes" type="textarea" :value="$invoice->notes" col="col-12 mb-0" maxlength="1000" />
            </x-card>
        </div>

        <div class="col-xl-3">
            <x-card title="Totals">
                <table class="table table-borderless totals-table mb-2">
                    <tr><td>Gross</td><td class="num" data-t="subtotal">0.00</td></tr>
                    <tr><td>Item discounts</td><td class="num" data-t="item_discount">0.00</td></tr>
                    @feature('tax')<tr><td>Tax</td><td class="num" data-t="tax">0.00</td></tr>@endfeature
                    <tr>
                        <td>Bill discount</td>
                        <td><input type="number" step="any" min="0" name="discount" class="form-control form-control-sm text-end" value="{{ old('discount', (float) $invoice->discount) }}"></td>
                    </tr>
                    <tr>
                        <td>{{ $isSale ? 'Delivery / other' : 'Freight / other' }}</td>
                        <td><input type="number" step="any" min="0" name="other_charges" class="form-control form-control-sm text-end" value="{{ old('other_charges', (float) $invoice->other_charges) }}"></td>
                    </tr>
                    <tr>
                        <td>Round off</td>
                        <td><input type="number" step="any" name="round_off" class="form-control form-control-sm text-end" value="{{ old('round_off', (float) $invoice->round_off) }}"></td>
                    </tr>
                    <tr class="grand"><td>Total</td><td class="num" data-t="total">0.00</td></tr>
                </table>

                <div class="mb-2">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="payment_mode" id="pm-cash" value="cash" @checked($mode === 'cash')>
                        <label class="btn btn-outline-success" for="pm-cash">{{ $type === 'sale' || $type === 'purchase_return' ? 'Paid in full' : ($isReturn ? 'Refund in full' : 'Paid in full') }}</label>
                        <input type="radio" class="btn-check" name="payment_mode" id="pm-credit" value="credit" @checked($mode !== 'cash')>
                        <label class="btn btn-outline-warning" for="pm-credit">On account</label>
                    </div>
                </div>

                <label class="form-label mb-1">{{ in_array($type, ['sale', 'purchase_return']) ? 'Amount received' : 'Amount paid' }}</label>
                <div class="input-group mb-2">
                    <select name="payment_account_id" class="form-select" style="max-width: 45%">
                        @foreach ($cashAccounts as $acc)
                            <option value="{{ $acc->id }}" @selected((int) old('payment_account_id') === $acc->id)>{{ $acc->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="any" min="0" name="paid_amount" class="form-control text-end" value="{{ $paidValue }}">
                </div>
                <table class="table table-borderless totals-table mb-0">
                    <tr><td>Balance due</td><td class="num fw-bold" data-t="due">0.00</td></tr>
                    <tr class="change-row d-none"><td>Change to return</td><td class="num text-success fw-bold" data-t="change">0.00</td></tr>
                </table>
                <div id="credit-warning" class="alert alert-warning py-1 px-2 small d-none"></div>

                <div class="d-grid gap-2 mt-3">
                    @if ($editing)
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Update</button>
                    @else
                        <button type="submit" name="after" value="print" class="btn btn-primary btn-lg"><i class="fas fa-print"></i> Save & Print <span class="kbd text-white border-light bg-transparent">F9</span></button>
                        <button type="submit" name="after" value="new" class="btn btn-outline-primary">Save & New</button>
                        <button type="submit" name="after" value="view" class="btn btn-link">Save & View</button>
                    @endif
                </div>
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
        type: @js($type),
        lines: @js($lines),
        returnOf: @js((bool) $invoice->return_of_id),
        tax: @js($business->feature('tax')),
        inclusive: @js($business->feature('prices_include_tax')),
        multiUnit: @js($business->feature('multi_unit')),
        creditLimits: @js($business->feature('credit_limits')),
        party: @js($invoice->party ? ['credit_limit' => (float) $invoice->party->credit_limit, 'walk_in' => $invoice->party->is_walk_in] : null),
    });
</script>
@endpush
