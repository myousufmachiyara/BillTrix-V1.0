@php
    [$type, $perm, $partyType, $label] = $meta;
    $isSale = in_array($type, ['sale', 'sale_return']);
    $isReturn = str_ends_with($type, '_return');
    $editing = $invoice->exists;
    $title = __(($editing ? 'Edit ' : 'New ').\App\Models\Invoice::TYPES[$type]).($editing ? ' '.$invoice->number : '');
    $mode = old('payment_mode', $invoice->payment_mode ?? 'credit');
    $paidValue = old('paid_amount', $editing ? $paidNow : 0);
@endphp
@extends('layouts.app')
@section('title', $title)
@section('header-actions')
    <a href="{{ route('invoices.index', $doc) }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> {{ __($label) }}</a>
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
                        <label class="form-label">{{ __($isSale ? 'Customer' : 'Supplier') }} <span class="text-danger">*</span></label>
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
                        <label class="form-label">{{ __('Date') }}</label>
                        <input type="date" name="date" class="form-control" value="{{ old('date', $invoice->date?->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3 col-6 mb-2 {{ $warehouses->count() > 1 ? '' : 'd-none' }}">
                        <label class="form-label">{{ __('Warehouse') }}</label>
                        <select name="warehouse_id" class="form-select" required>
                            @foreach ($warehouses as $id => $name)
                                <option value="{{ $id }}" @selected((int) old('warehouse_id', $invoice->warehouse_id) === $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">{{ __($isSale ? 'Customer PO / Ref.' : 'Supplier bill no.') }}</label>
                        <input type="text" name="reference" class="form-control" value="{{ old('reference', $invoice->reference) }}" maxlength="100">
                    </div>
                    @if ($type === 'sale')
                        <div class="col-md-4 mb-2 walkin-fields">
                            <label class="form-label">{{ __('Name on bill') }} <small class="text-muted">({{ __('walk-in') }})</small></label>
                            <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name', $invoice->customer_name) }}" maxlength="100">
                        </div>
                        <div class="col-md-3 mb-2 walkin-fields">
                            <label class="form-label">{{ __('Phone') }}</label>
                            <input type="text" name="customer_phone" class="form-control" value="{{ old('customer_phone', $invoice->customer_phone) }}" maxlength="30">
                        </div>
                    @endif
                    @if (! $invoice->return_of_id && $linked)
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0">
                                @if (collect($lines)->contains(fn ($l) => ! empty($l['delivery_item_id'])))
                                    {{ __('Billing delivery challan(s)') }} <strong>{{ $invoice->reference }}</strong>. Quantities come from the challans; you can still set rates and discounts.
                                @else
                                    {{ __('Billing against order') }} <strong>{{ $invoice->relationLoaded('order') ? $invoice->order?->number : $invoice->reference }}</strong>. Lines show what is still pending; reduce or remove what you are not billing now.
                                @endif
                            </div>
                        </div>
                    @endif
                    @if ($invoice->return_of_id)
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0">
                                {{ __('Returning items from') }} <strong>{{ $invoice->returnOf?->number }}</strong>
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
                                    <input type="text" id="barcode" class="form-control scan-box" placeholder="{{ __('Scan barcode or type SKU, Enter  (qty*code works)') }}">
                                </div>
                            </div>
                            <div class="col-md-7">
                                <select id="product-search" data-placeholder="{{ __('Search product by name…  (F3)') }}"></select>
                            </div>
                        </div>
                    </div>
                @endunless
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="lines">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Item') }}</th>
                                <th @class(['d-none' => ! $business->feature('multi_unit')]) style="width:110px">{{ __('Unit') }}</th>
                                <th class="num" style="width:100px">{{ __('Qty') }}</th>
                                <th class="num" style="width:120px">{{ __('Rate') }}</th>
                                <th class="num" style="width:80px">{{ __('Disc %') }}</th>
                                <th @class(['num', 'd-none' => ! $business->feature('tax')]) style="width:80px">{{ __('Tax %') }}</th>
                                <th class="num">{{ __('Amount') }}</th>
                                <th style="width:30px"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="card-body pt-2 small text-muted">
                    <span data-t="count">0</span> {{ __('item(s)') }} ·
                    <span class="kbd">F2</span> {{ __('scan') }} · <span class="kbd">F3</span> {{ __('search') }} · <span class="kbd">F9</span> {{ __('save') }}
                </div>
            </x-card>

            <x-card>
                <x-input name="notes" label="{{ __('Notes') }}" type="textarea" :value="$invoice->notes" col="col-12 mb-0" maxlength="1000" />
            </x-card>
        </div>

        <div class="col-xl-3">
            <x-card title="{{ __('Totals') }}">
                <table class="table table-borderless totals-table mb-2">
                    <tr><td>{{ __('Gross') }}</td><td class="num" data-t="subtotal">0.00</td></tr>
                    <tr><td>{{ __('Item discounts') }}</td><td class="num" data-t="item_discount">0.00</td></tr>
                    @feature('tax')<tr><td>{{ __('Tax') }}</td><td class="num" data-t="tax">0.00</td></tr>@endfeature
                    <tr>
                        <td>{{ __('Bill discount') }}</td>
                        <td><input type="number" step="any" min="0" name="discount" class="form-control form-control-sm text-end" value="{{ old('discount', (float) $invoice->discount) }}"></td>
                    </tr>
                    <tr>
                        <td>{{ __($isSale ? 'Delivery / other' : 'Freight / other') }}</td>
                        <td><input type="number" step="any" min="0" name="other_charges" class="form-control form-control-sm text-end" value="{{ old('other_charges', (float) $invoice->other_charges) }}"></td>
                    </tr>
                    <tr>
                        <td>{{ __('Round off') }}</td>
                        <td><input type="number" step="any" name="round_off" class="form-control form-control-sm text-end" value="{{ old('round_off', (float) $invoice->round_off) }}"></td>
                    </tr>
                    @feature('wht')
                        <tr>
                            <td>{{ __('WHT') }} <small class="text-muted">{{ $business->setting($isSale ? 'wht_sales_label' : 'wht_purchase_label') }}</small>
                                <div class="input-group input-group-sm mt-1" style="width:95px"><input type="number" step="any" min="0" max="100" name="wht_rate" class="form-control text-end" value="{{ old('wht_rate', $invoice->exists ? (float) $invoice->wht_rate : '') }}"><span class="input-group-text">%</span></div></td>
                            <td class="num align-bottom"><span data-t="wht">0.00</span></td>
                        </tr>
                    @endfeature
                    @if ($type === 'sale' && $business->setting('fbr_mode') === 'pos')
                        <tr><td>{{ __('FBR POS fee') }}</td><td class="num" data-t="fee">0.00</td></tr>
                    @endif
                    <tr class="grand"><td>{{ __('Total') }}</td><td class="num" data-t="total">0.00</td></tr>
                </table>

                <div class="mb-2">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="payment_mode" id="pm-cash" value="cash" @checked($mode === 'cash')>
                        <label class="btn btn-outline-success" for="pm-cash">{{ __($isReturn && $type !== 'purchase_return' ? 'Refund in full' : 'Paid in full') }}</label>
                        <input type="radio" class="btn-check" name="payment_mode" id="pm-credit" value="credit" @checked($mode !== 'cash')>
                        <label class="btn btn-outline-warning" for="pm-credit">{{ __('On account') }}</label>
                    </div>
                </div>

                <label class="form-label mb-1">{{ __(in_array($type, ['sale', 'purchase_return']) ? 'Amount received' : 'Amount paid') }}</label>
                <div class="input-group mb-2">
                    <select name="payment_account_id" class="form-select" style="max-width: 45%">
                        @foreach ($cashAccounts as $acc)
                            <option value="{{ $acc->id }}" @selected((int) old('payment_account_id') === $acc->id)>{{ $acc->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="any" min="0" name="paid_amount" class="form-control text-end" value="{{ $paidValue }}">
                </div>
                <table class="table table-borderless totals-table mb-0">
                    <tr><td>{{ __('Balance due') }}</td><td class="num fw-bold" data-t="due">0.00</td></tr>
                    <tr class="change-row d-none"><td>{{ __('Change to return') }}</td><td class="num text-success fw-bold" data-t="change">0.00</td></tr>
                </table>
                <div id="credit-warning" class="alert alert-warning py-1 px-2 small d-none"></div>

                <div class="d-grid gap-2 mt-3">
                    @if ($editing)
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> {{ __('Update') }}</button>
                    @else
                        <button type="submit" name="after" value="print" class="btn btn-primary btn-lg"><i class="fas fa-print"></i> {{ __('Save & Print') }} <span class="kbd text-white border-light bg-transparent">F9</span></button>
                        <button type="submit" name="after" value="new" class="btn btn-outline-primary">{{ __('Save & New') }}</button>
                        <button type="submit" name="after" value="view" class="btn btn-link">{{ __('Save & View') }}</button>
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
        batches: @js($business->feature('batches')),
        party: @js($invoice->party ? ['credit_limit' => (float) $invoice->party->credit_limit, 'walk_in' => $invoice->party->is_walk_in, 'is_filer' => $invoice->party->is_filer, 'wht_exempt' => $invoice->party->wht_exempt] : null),
        wht: @js($business->feature('wht') ? ['filer' => (float) $business->setting($isSale ? 'wht_sales_filer' : 'wht_purchase_filer'), 'nonfiler' => (float) $business->setting($isSale ? 'wht_sales_nonfiler' : 'wht_purchase_nonfiler')] : null),
        fee: @js($type === 'sale' && $business->setting('fbr_mode') === 'pos' ? (float) $business->setting('fbr_service_fee', 1) : 0),
    });
</script>
@endpush
