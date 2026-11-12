@extends('layouts.app')
@section('title', __('Barcode Labels'))
@section('content')
<form method="POST" action="{{ route('labels.print') }}" target="_blank" id="labels-form">@csrf
<div class="row">
    <div class="col-lg-8">
        <x-card title="Items">
            <div class="mb-2"><x-product-select name="pick" placeholder="Search product…" /></div>
            <p class="small text-muted">{{ __('Products with variations are listed once per variation. Items without a barcode print their SKU.') }}</p>
            <table class="table table-sm align-middle" id="label-lines">
                <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Barcode') }}</th><th style="width:120px">{{ __('Labels') }}</th><th></th></tr></thead>
                <tbody></tbody>
            </table>
            <div class="text-muted small" id="label-empty">{{ __('Add products to print their labels.') }}</div>
        </x-card>
    </div>
    <div class="col-lg-4">
        <x-card title="Layout">
            <div class="row">
                <x-select name="size" label="Label size" :options="collect($sizes)->map(fn ($s) => $s[0])" value="a4-3x8" col="col-12" />
                <x-check name="show_name" label="Show name" :checked="true" col="col-12" />
                <x-check name="show_price" label="Show price" :checked="true" col="col-12" />
            </div>
            <button class="btn btn-primary w-100"><i class="fas fa-print"></i> {{ __('Print labels') }}</button>
        </x-card>
    </div>
</div>
</form>
@endsection
@push('scripts')
<script>
    (function () {
        let n = 0;
        const $tb = $('#label-lines tbody');
        function add(r) {
            const key = r.product_id + '-' + (r.variant_id || 0);
            if ($tb.find(`tr[data-key="${key}"]`).length) return;
            const i = n++;
            $tb.append(`<tr data-key="${key}">
                <td><input type="hidden" name="items[${i}][product_id]" value="${r.product_id}">${r.variant_id ? `<input type="hidden" name="items[${i}][variant_id]" value="${r.variant_id}">` : ''}${BT.esc(r.name)}</td>
                <td class="small">${r.code ? BT.esc(r.code) : '<span class="text-danger">{{ __('No barcode or SKU') }}</span>'}</td>
                <td><input type="number" min="0" max="1000" name="items[${i}][copies]" class="form-control form-control-sm" value="${r.copies || 1}"></td>
                <td><button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td></tr>`);
            $('#label-empty').addClass('d-none');
        }
        @js($rows).forEach(add);
        $('[name=pick]').on('select2:select', function (e) {
            const p = e.params.data;
            add({ product_id: p.product_id || p.id, variant_id: p.variant_id, name: p.name, code: p.barcode || p.sku, copies: Math.max(1, Math.ceil(p.stock || 1)) });
            $(this).val(null).trigger('change');
        });
    })();
</script>
@endpush
