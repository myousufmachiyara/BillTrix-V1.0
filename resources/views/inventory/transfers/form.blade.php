@extends('layouts.app')
@section('title', 'New Stock Transfer')
@section('content')
<form method="POST" action="{{ route('stock-transfers.store') }}" id="tr-form" autocomplete="off">
    @csrf
    <x-card>
        <div class="row">
            @php $opts = $warehouses->mapWithKeys(fn ($w) => [$w->id => $w->name.' ('.$w->branch->name.')']); @endphp
            <x-select name="from_warehouse_id" label="From" :options="$opts" required col="col-md-3" />
            <x-select name="to_warehouse_id" label="To" :options="$opts" :value="$warehouses->skip(1)->first()?->id" required col="col-md-3" />
            <x-input name="date" label="Date" type="date" :value="now()->toDateString()" required col="col-md-2" />
            <x-input name="notes" label="Notes" col="col-md-4" />
        </div>
        <div class="col-md-6 mb-2"><select id="tr-product" data-placeholder="Add product…"></select></div>
        <table class="table table-sm" id="tr-lines">
            <thead class="table-light"><tr><th>Product</th><th class="num">Available</th><th style="width:130px">Unit</th><th class="num" style="width:130px">Quantity</th><th></th></tr></thead>
            <tbody></tbody>
        </table>
        <div class="text-end"><a href="{{ route('stock-transfers.index') }}" class="btn btn-default">Cancel</a> <button class="btn btn-primary"><i class="fas fa-exchange-alt"></i> Transfer</button></div>
    </x-card>
</form>
@endsection
@push('scripts')
<script>
(function ($) {
    let n = 0;
    BT.productSelect($('#tr-product'), () => ({ warehouse_id: $('[name=from_warehouse_id]').val() }));
    $('#tr-product').on('select2:select', function (e) {
        const p = e.params.data, i = n++;
        const units = p.units.map(u => `<option value="${u.unit_id}">${BT.esc(u.name)}${u.factor !== 1 ? ' (' + BT.qty(u.factor) + ')' : ''}</option>`).join('');
        $('#tr-lines tbody').append(`<tr><td><input type="hidden" name="items[${i}][product_id]" value="${p.id}">${BT.esc(p.name)}</td>
            <td class="num">${BT.qty(p.stock || 0)}</td>
            <td><select name="items[${i}][unit_id]" class="form-select form-select-sm">${units}</select></td>
            <td><input type="number" step="any" min="0" name="items[${i}][quantity]" class="form-control form-control-sm text-end" required></td>
            <td><button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td></tr>`);
        $(this).val(null).trigger('change');
        $('#tr-lines tbody tr:last input[type=number]').trigger('focus');
    });
    $('#tr-form').on('keydown', 'input', (e) => { if (e.key === 'Enter') e.preventDefault(); });
})(jQuery);
</script>
@endpush
