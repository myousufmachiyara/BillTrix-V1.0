@php $titles = ['opening' => 'Opening Stock', 'count' => 'Physical Stock Count', 'adjustment' => 'Stock Adjustment']; @endphp
@extends('layouts.app')
@section('title', __($titles[$type]))
@section('content')
<form method="POST" action="{{ route('stock-adjustments.store') }}" id="adj-form" autocomplete="off">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <x-card>
        <div class="row">
            <x-select name="warehouse_id" label="Warehouse" :options="$warehouses" required col="col-md-3" />
            <x-input name="date" label="Date" type="date" :value="now()->toDateString()" required col="col-md-3" />
            @if ($type === 'adjustment')
                <x-select name="reason" label="Reason" :options="\App\Models\StockAdjustment::REASONS" required col="col-md-3" placeholder="Choose…" />
            @endif
            <x-input name="notes" label="Notes" col="col-md-3" />
        </div>
        <div class="alert alert-info py-2 small">
            @if ($type === 'opening') {{ __('Enter the stock you already have and what it cost. This also posts the opening value to your books.') }}
            @elseif ($type === 'count') {{ __('Enter what you actually counted. BillTrix posts the difference against the system quantity as a gain or loss.') }}
            @else {{ __('Use a positive quantity to add stock (found / excess) and a negative quantity to remove it (damaged, expired, lost).') }}
            @endif
        </div>
        <div class="row g-2 mb-2">
            <div class="col-md-6"><select id="adj-product" data-placeholder="{{ __('Add product…') }}"></select></div>
        </div>
        <table class="table table-sm" id="adj-lines">
            <thead class="table-light"><tr><th>{{ __('Product') }}</th>
                @if ($type === 'count')<th class="num">{{ __('System qty') }}</th><th class="num" style="width:140px">{{ __('Counted qty') }}</th><th class="num">{{ __('Difference') }}</th>
                @else<th class="num" style="width:140px">{{ __('Quantity') }} {{ $type === 'adjustment' ? '(+/−)' : '' }}</th><th class="num" style="width:140px">{{ __('Unit cost') }}</th>@endif
                <th></th></tr></thead>
            <tbody></tbody>
        </table>
        <div class="text-end"><a href="{{ route('stock-adjustments.index') }}" class="btn btn-default">{{ __('Cancel') }}</a> <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Post') }}</button></div>
    </x-card>
</form>
@endsection
@push('scripts')
<script>
(function ($) {
    const type = @js($type);
    let n = 0;
    BT.productSelect($('#adj-product'), () => ({ warehouse_id: $('[name=warehouse_id]').val() }));
    $('#adj-product').on('select2:select', function (e) {
        const p = e.params.data, i = n++;
        if ($(`#adj-lines [data-pid=${p.id}]`).length) { $(this).val(null).trigger('change'); return; }
        const unit = (p.units[0] || {}).name || '';
        const cells = type === 'count'
            ? `<td class="num">${BT.qty(p.stock || 0)}</td><td><input type="number" step="any" min="0" name="items[${i}][counted]" class="form-control form-control-sm text-end cnt" data-sys="${p.stock || 0}" required></td><td class="num diff"></td>`
            : `<td><input type="number" step="any" name="items[${i}][quantity]" class="form-control form-control-sm text-end" ${type === 'opening' ? 'min="0"' : ''} required></td>
               <td><input type="number" step="any" min="0" name="items[${i}][unit_cost]" class="form-control form-control-sm text-end" value="${p.avg_cost || p.purchase_price || ''}"></td>`;
        const batch = p.track_batches && type !== 'count'
            ? `<div class="d-flex gap-1 mt-1"><input name="items[${i}][batch_no]" class="form-control form-control-sm" style="max-width:130px" placeholder="Batch no.${type === 'adjustment' ? ' (blank = FEFO)' : ''}">`
              + `<input type="date" name="items[${i}][expiry_date]" class="form-control form-control-sm" style="max-width:150px" title="Expiry (for additions)"></div>` : '';
        $('#adj-lines tbody').append(`<tr data-pid="${p.id}"><td><input type="hidden" name="items[${i}][product_id]" value="${p.id}">${BT.esc(p.name)} <small class="text-muted">${BT.esc(unit)}</small>${batch}</td>${cells}
            <td><button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td></tr>`);
        $(this).val(null).trigger('change');
        $('#adj-lines tbody tr:last input:visible:first').trigger('focus');
    });
    $(document).on('input', '.cnt', function () {
        const d = BT.num(this.value) - +$(this).data('sys');
        $(this).closest('tr').find('.diff').text(this.value === '' ? '' : (d > 0 ? '+' : '') + BT.qty(d)).toggleClass('text-danger', d < 0).toggleClass('text-success', d > 0);
    });
    $('#adj-form').on('keydown', 'input', (e) => { if (e.key === 'Enter') e.preventDefault(); });
})(jQuery);
</script>
@endpush
