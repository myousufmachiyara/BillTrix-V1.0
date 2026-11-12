@php $editing = $delivery->exists; @endphp
@extends('layouts.app')
@section('title', $editing ? __('Edit Challan').' '.$delivery->number : __('New Delivery Challan'))
@section('content')
<form method="POST" action="{{ $editing ? route('deliveries.update', $delivery) : route('deliveries.store') }}" id="dc-form" autocomplete="off">
    @csrf @if ($editing) @method('PUT') @endif
    @if ($order)<input type="hidden" name="order_id" value="{{ $order->id }}">@endif
    <x-card>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">{{ __('Customer') }} <span class="text-danger">*</span></label>
                @if ($order)
                    <input type="text" class="form-control" value="{{ $delivery->party->name }}" disabled><input type="hidden" name="party_id" value="{{ $delivery->party_id }}">
                @else
                    <x-party-select type="customer" :party="$delivery->party" required />
                @endif
            </div>
            <x-input name="date" label="Date" type="date" :value="$delivery->date?->toDateString()" required col="col-md-2" />
            <x-select name="warehouse_id" label="Dispatch from" :options="$warehouses" :value="$delivery->warehouse_id" required col="col-md-2" />
            <x-input name="vehicle_no" label="Vehicle no." :value="$delivery->vehicle_no" col="col-md-2" />
            <x-input name="driver" label="Driver / transporter" :value="$delivery->driver" col="col-md-2" />
            <x-input name="ship_to" label="Deliver to (address)" :value="$delivery->ship_to ?? $delivery->party?->address" col="col-md-6" />
            <x-input name="notes" label="Notes" :value="$delivery->notes" col="col-md-6" />
        </div>
        @if ($order)
            <div class="alert alert-info py-2 small">{{ __('Delivering against order') }} <strong>{{ $order->number }}</strong>. Enter what is going out now; leave the rest at 0 for a later challan.</div>
        @else
            <div class="row g-2 mb-2"><div class="col-md-6"><select id="dc-product" data-placeholder="{{ __('Add product…') }}"></select></div></div>
        @endif
        <table class="table table-sm" id="dc-lines">
            <thead class="table-light"><tr><th>{{ __('Product') }}</th>@if ($order)<th class="num">{{ __('Ordered') }}</th><th class="num">{{ __('Pending') }}</th>@endif<th style="width:130px">{{ __('Unit') }}</th><th class="num" style="width:130px">{{ __('Deliver now') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($rows as $i => $r)
                <tr>
                    <td><input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $r['product_id'] }}">@if (! empty($r['variant_id']))<input type="hidden" name="items[{{ $i }}][variant_id]" value="{{ $r['variant_id'] }}">@endif<input type="hidden" name="items[{{ $i }}][order_item_id]" value="{{ $r['order_item_id'] }}">{{ $r['name'] }}</td>
                    @if ($order)<td class="num">{{ $r['ordered'] }}</td><td class="num">{{ $r['pending'] !== null ? qty($r['pending']) : '' }}</td>@endif
                    <td>
                        @if ($r['order_item_id'])
                            <input type="hidden" name="items[{{ $i }}][unit_id]" value="{{ $r['unit_id'] }}">{{ collect($r['units'])->firstWhere('unit_id', $r['unit_id'])['name'] ?? '' }}
                        @else
                            <select name="items[{{ $i }}][unit_id]" class="form-select form-select-sm">@foreach ($r['units'] as $u)<option value="{{ $u['unit_id'] }}" @selected($u['unit_id'] === $r['unit_id'])>{{ $u['name'] }}</option>@endforeach</select>
                        @endif
                    </td>
                    <td><input type="number" step="any" min="0" @if ($r['pending'] !== null) max="{{ $r['pending'] }}" @endif name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", $r['quantity'] ?: '') }}" class="form-control form-control-sm text-end"></td>
                    <td>@if (! $r['order_item_id'])<button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if ($order)<button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="fill-all">{{ __('Deliver everything pending') }}</button>@endif
        <div class="text-end"><a href="{{ url()->previous() }}" class="btn btn-default">{{ __('Cancel') }}</a> <button class="btn btn-primary"><i class="fas fa-truck"></i> {{ __('Save & dispatch') }}</button></div>
    </x-card>
</form>
@endsection
@push('scripts')
<script>
(function ($) {
    let n = {{ count($rows) }};
    $('#fill-all').on('click', () => $('#dc-lines input[max]').each(function () { this.value = this.max; }));
    const $p = $('#dc-product');
    if ($p.length) {
        BT.productSelect($p, () => ({ warehouse_id: $('[name=warehouse_id]').val() }));
        $p.on('select2:select', function (e) {
            const d = e.params.data, i = n++;
            const units = d.units.map(u => `<option value="${u.unit_id}">${BT.esc(u.name)}</option>`).join('');
            $('#dc-lines tbody').append(`<tr><td><input type="hidden" name="items[${i}][product_id]" value="${d.product_id || d.id}">${d.variant_id ? `<input type="hidden" name="items[${i}][variant_id]" value="${d.variant_id}">` : ''}${BT.esc(d.name)} <span class="badge bg-light text-dark">${BT.qty(d.stock || 0)} in stock</span></td>
                <td><select name="items[${i}][unit_id]" class="form-select form-select-sm">${units}</select></td>
                <td><input type="number" step="any" min="0" name="items[${i}][quantity]" class="form-control form-control-sm text-end" required></td>
                <td><button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td></tr>`);
            $(this).val(null).trigger('change');
            $('#dc-lines tbody tr:last input[type=number]').trigger('focus');
        });
    }
    $('#dc-form').on('keydown', 'input', (e) => { if (e.key === 'Enter') e.preventDefault(); });
})(jQuery);
</script>
@endpush
