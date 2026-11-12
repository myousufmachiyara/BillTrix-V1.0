@extends('layouts.app')
@section('title', $product->exists ? __('Edit').' '.$product->name : __('Add Product'))
@section('content')
@php
    $packs = old('packs', $product->exists ? $product->units->map(fn ($u) => $u->only(['unit_id', 'factor', 'barcode', 'sale_price', 'purchase_price']))->all() : []);
    $levelPrices = old('prices', $product->exists ? $product->prices->pluck('price', 'price_level_id')->all() : []);
@endphp
<form method="POST" action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" enctype="multipart/form-data" autocomplete="off">
    @csrf @if ($product->exists) @method('PUT') @endif
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Product">
                <div class="row">
                    <x-input name="name" label="Name" :value="$product->name" required col="col-md-8" autofocus />
                    <x-input name="brand" label="Brand" :value="$product->brand" col="col-md-4" />
                    <x-input name="sku" label="SKU / item code" :value="$product->sku" col="col-md-4" />
                    <x-input name="barcode" label="Barcode" :value="$product->barcode" col="col-md-4" />
                    @if ($business->setting('fbr_mode') !== 'off' || $business->feature('tax'))
                        <x-input name="hs_code" label="HS / PCT code" :value="$product->hs_code" col="col-md-4" help="Required for FBR reporting." placeholder="e.g. 3401.1100" />
                    @endif
                    <x-select name="category_id" label="Category" :options="$categories" :value="$product->category_id" placeholder="—" col="col-md-4" />
                    <x-select name="unit_id" label="Base unit (smallest)" :options="$units->pluck('name', 'id')" :value="$product->unit_id" required col="col-md-4" help="Stock is kept in this unit." />
                    @feature('tax')
                        <x-select name="tax_rate_id" label="Tax rate" :options="$taxRates->pluck('name', 'id')" :value="$product->tax_rate_id" placeholder="No tax" col="col-md-4" />
                    @endfeature
                    <x-input name="reorder_level" label="Reorder level" type="number" step="any" min="0" :value="(float) $product->reorder_level" col="col-md-4" help="Alert when stock falls to this." />
                    <x-input name="description" label="Description" type="textarea" :value="$product->description" col="col-12" />
                </div>
            </x-card>

            <x-card title="Pricing (per base unit)">
                <div class="row">
                    <x-input name="purchase_price" label="Purchase price" type="number" step="any" min="0" :value="(float) $product->purchase_price" col="col-md-4" />
                    <x-input name="sale_price" label="Sale price" type="number" step="any" min="0" :value="(float) $product->sale_price" col="col-md-4" required />
                    <x-input name="min_sale_price" label="Minimum sale price" type="number" step="any" min="0" :value="(float) $product->min_sale_price" col="col-md-4" />
                    @feature('price_levels')
                        @foreach ($priceLevels as $level)
                            <x-input name="prices[{{ $level->id }}]" :label="$level->name.' '.__('price')" type="number" step="any" min="0" :value="$levelPrices[$level->id] ?? null" col="col-md-4" placeholder="Same as sale price" />
                        @endforeach
                    @endfeature
                </div>
            </x-card>

            @feature('variants')
            @php
                $hasVariants = (bool) old('has_variants', $product->has_variants);
                $rows = old('variants', $variantRows);
            @endphp
            <x-card title="Variations">
                <input type="hidden" name="has_variants_shown" value="1">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="has_variants" value="1" id="has_variants" @checked($hasVariants) @disabled($variantsLocked)>
                    <label class="form-check-label" for="has_variants">{{ __('This product comes in variations (size, colour…)') }}</label>
                    @if ($variantsLocked && $hasVariants)<input type="hidden" name="has_variants" value="1">@endif
                </div>
                @if ($variantsLocked)<p class="small text-muted mb-2">{{ __('Variations cannot be switched on or off once the product has stock movements.') }}</p>@endif

                <div id="variant-builder" class="{{ $hasVariants ? '' : 'd-none' }}">
                    @if ($attributes->isEmpty())
                        <div class="alert alert-info py-2 small">{{ __('First add attributes such as Size or Colour.') }}
                            @can('products.view')<a href="{{ route('attributes.index') }}">{{ __('Attributes') }}</a>@endcan</div>
                    @else
                        <p class="small text-muted mb-2">{{ __('Tick the values this product comes in, then create the variations. Each gets its own SKU, barcode, price and stock. Leave SKU or barcode blank to create them automatically; blank prices use the product price.') }}</p>
                        <div class="row g-2 mb-2" id="attr-pickers">
                            @foreach ($attributes as $a)
                                <div class="col-md-6">
                                    <div class="small fw-semibold mb-1">{{ $a->name }}</div>
                                    @foreach ($a->values as $v)
                                        <label class="btn btn-sm btn-outline-secondary mb-1 attr-chip">
                                            <input type="checkbox" class="d-none" data-attr="{{ $a->id }}" data-attr-sort="{{ $a->sort }}" value="{{ $v->id }}" data-label="{{ $v->value }}"
                                                @checked(collect($rows)->contains(fn ($r) => in_array($v->id, array_map('intval', $r['value_ids'] ?? []), true)))> {{ $v->value }}
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mb-2" id="make-variants"><i class="fas fa-th"></i> {{ __('Create variations') }}</button>
                    @endif
                    <div class="table-responsive">
                    <table class="table table-sm align-middle" id="variants">
                        <thead><tr>
                            <th>{{ __('Variation') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Barcode') }}</th><th>{{ __('Sale price') }}</th><th>{{ __('Purchase price') }}</th>
                            <th>{{ __('Reorder level') }}</th>@unless ($product->exists)@can('stock.adjust')<th>{{ __('Opening qty') }}</th>@endcan @endunless
                            <th>{{ __('Active') }}</th><th></th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                    </div>
                </div>
            </x-card>
            @endfeature

            @feature('multi_unit')
            <x-card title="Packing / alternate units">
                <p class="small text-muted">{{ __('Example: base unit') }} <em>{{ __('Pcs') }}</em>, 1 <em>{{ __('Carton') }}</em> = 24 {{ __('Pcs') }}. {{ __('Prices left blank are calculated from the base price.') }}</p>
                <table class="table table-sm" id="packs">
                    <thead><tr><th>{{ __('Unit') }}</th><th>{{ __('Contains (base units)') }}</th><th>{{ __('Barcode') }}</th><th>{{ __('Sale price') }}</th><th>{{ __('Purchase price') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($packs as $i => $pack)
                        <tr>
                            <td><select name="packs[{{ $i }}][unit_id]" class="form-select form-select-sm">@foreach ($units as $u)<option value="{{ $u->id }}" @selected((int) $pack['unit_id'] === $u->id)>{{ $u->name }}</option>@endforeach</select></td>
                            <td><input name="packs[{{ $i }}][factor]" type="number" step="any" min="1" class="form-control form-control-sm" value="{{ (float) $pack['factor'] }}" required></td>
                            <td><input name="packs[{{ $i }}][barcode]" class="form-control form-control-sm" value="{{ $pack['barcode'] }}"></td>
                            <td><input name="packs[{{ $i }}][sale_price]" type="number" step="any" class="form-control form-control-sm" value="{{ $pack['sale_price'] !== null ? (float) $pack['sale_price'] : '' }}"></td>
                            <td><input name="packs[{{ $i }}][purchase_price]" type="number" step="any" class="form-control form-control-sm" value="{{ $pack['purchase_price'] !== null ? (float) $pack['purchase_price'] : '' }}"></td>
                            <td><button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-pack"><i class="fas fa-plus"></i> {{ __('Add unit') }}</button>
                <template id="pack-tpl">
                    <tr>
                        <td><select name="packs[__][unit_id]" class="form-select form-select-sm">@foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></td>
                        <td><input name="packs[__][factor]" type="number" step="any" min="1" class="form-control form-control-sm" required></td>
                        <td><input name="packs[__][barcode]" class="form-control form-control-sm"></td>
                        <td><input name="packs[__][sale_price]" type="number" step="any" class="form-control form-control-sm"></td>
                        <td><input name="packs[__][purchase_price]" type="number" step="any" class="form-control form-control-sm"></td>
                        <td><button type="button" class="btn btn-link text-danger p-0" onclick="this.closest('tr').remove()"><i class="fas fa-times"></i></button></td>
                    </tr>
                </template>
            </x-card>
            @endfeature
        </div>

        <div class="col-lg-4">
            <x-card title="Options">
                <div class="row">
                    <x-check name="track_stock" label="Track stock" :checked="$product->track_stock" col="col-12" help="Turn off for services or items you don't count." />
                    @feature('batches')
                        <x-check name="track_batches" label="Track batches & expiry" :checked="$product->track_batches" col="col-12" help="Batch number and expiry are captured on purchases; sales pick the earliest expiry first." />
                    @endfeature
                    <x-check name="is_active" label="Active" :checked="$product->is_active" col="col-12" />
                    <x-input name="image" label="Image" type="file" accept="image/*" col="col-12" />
                    @if ($product->image_path)<div class="col-12"><img src="{{ asset('storage/'.$product->image_path) }}" class="img-thumbnail" style="max-height:120px" alt=""></div>@endif
                </div>
            </x-card>
            @unless ($product->exists)
                @can('stock.adjust')
                <x-card title="Opening stock">
                    <div class="row">
                        <x-input name="opening_qty" label="Quantity (base unit)" type="number" step="any" min="0" col="col-6" />
                        <x-input name="opening_cost" label="Cost per unit" type="number" step="any" min="0" col="col-6" placeholder="= purchase price" />
                        @if ($warehouses->count() > 1)
                            <x-select name="opening_warehouse_id" label="Warehouse" :options="$warehouses" col="col-12" />
                        @endif
                    </div>
                </x-card>
                @endcan
            @endunless
            <div class="d-grid gap-2">
                <button class="btn btn-primary btn-lg"><i class="fas fa-save"></i> {{ __('Save') }}</button>
                @unless ($product->exists)<button class="btn btn-outline-primary" name="save_new" value="1">{{ __('Save & add another') }}</button>@endunless
            </div>
        </div>
    </div>
</form>
@endsection
@push('scripts')
<script>
    @feature('variants')
    (function () {
        const rows = @js(array_values($rows ?? []));
        const valueLabel = {};
        document.querySelectorAll('#attr-pickers input').forEach((c) => { valueLabel[c.value] = { label: c.dataset.label, attr: +c.dataset.attr, sort: +c.dataset.attrSort }; });
        const opening = {{ (! $product->exists && auth()->user()->can('stock.adjust')) ? 'true' : 'false' }};
        const $tbody = document.querySelector('#variants tbody');
        let n = 0;
        const esc = (v) => BT.esc(v === null || v === undefined ? '' : String(v));
        const key = (ids) => ids.map(Number).sort((a, b) => a - b).join('-');
        const nameOf = (ids) => ids.map((id) => valueLabel[id]).filter(Boolean).sort((a, b) => a.sort - b.sort || a.attr - b.attr).map((v) => v.label).join(' / ');

        function addRow(r) {
            const i = n++;
            const ids = (r.value_ids || []).map(Number);
            const hidden = ids.map((id) => `<input type="hidden" name="variants[${i}][value_ids][]" value="${id}">`).join('')
                + (r.id ? `<input type="hidden" name="variants[${i}][id]" value="${r.id}">` : '');
            const stock = r.stock !== undefined && r.stock !== null ? `<div class="small text-muted">${BT.qty(r.stock)} {{ __('in stock') }}</div>` : '';
            const tr = document.createElement('tr');
            tr.dataset.key = key(ids);
            tr.innerHTML = `<td class="text-nowrap">${hidden}<span class="fw-semibold">${esc(r.name || nameOf(ids))}</span>${stock}</td>
                <td><input name="variants[${i}][sku]" class="form-control form-control-sm" style="min-width:120px" value="${esc(r.sku)}" placeholder="{{ __('auto') }}"></td>
                <td><input name="variants[${i}][barcode]" class="form-control form-control-sm" style="min-width:130px" value="${esc(r.barcode)}" placeholder="{{ __('auto') }}"></td>
                <td><input name="variants[${i}][sale_price]" type="number" step="any" min="0" class="form-control form-control-sm" value="${esc(r.sale_price)}" placeholder="{{ __('= product') }}"></td>
                <td><input name="variants[${i}][purchase_price]" type="number" step="any" min="0" class="form-control form-control-sm" value="${esc(r.purchase_price)}" placeholder="{{ __('= product') }}"></td>
                <td><input name="variants[${i}][reorder_level]" type="number" step="any" min="0" class="form-control form-control-sm" value="${esc(r.reorder_level)}" placeholder="{{ __('= product') }}"></td>
                ${opening ? `<td><input name="variants[${i}][opening_qty]" type="number" step="any" min="0" class="form-control form-control-sm" value="${esc(r.opening_qty)}"></td>` : ''}
                <td class="text-center"><input type="checkbox" class="form-check-input" name="variants[${i}][is_active]" value="1" ${r.is_active === false || r.is_active === '0' ? '' : 'checked'}></td>
                <td><button type="button" class="btn btn-link text-danger p-0" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button></td>`;
            tr.querySelector('button').addEventListener('click', () => tr.remove());
            $tbody.appendChild(tr);
        }
        rows.forEach((r) => addRow(Object.assign({}, r, { is_active: r.is_active === undefined ? true : (r.is_active === true || r.is_active === '1' || r.is_active === 1) })));

        // Every combination of the ticked values (one value per attribute).
        document.getElementById('make-variants')?.addEventListener('click', () => {
            const groups = {};
            document.querySelectorAll('#attr-pickers input:checked').forEach((c) => { (groups[c.dataset.attr] = groups[c.dataset.attr] || []).push(+c.value); });
            const lists = Object.values(groups);
            if (!lists.length) return;
            const combos = lists.reduce((acc, list) => acc.flatMap((a) => list.map((v) => a.concat(v))), [[]]);
            const have = new Set([...$tbody.querySelectorAll('tr')].map((tr) => tr.dataset.key));
            combos.forEach((ids) => { if (!have.has(key(ids))) addRow({ value_ids: ids, is_active: true }); });
        });
        document.querySelectorAll('#attr-pickers input').forEach((c) => {
            const sync = () => c.closest('label').classList.toggle('active', c.checked);
            c.addEventListener('change', sync); sync();
        });
        const toggle = document.getElementById('has_variants');
        const batches = document.querySelector('[name=track_batches][type=checkbox]');
        const apply = () => {
            document.getElementById('variant-builder').classList.toggle('d-none', !toggle.checked);
            if (batches) { if (toggle.checked) batches.checked = false; batches.disabled = toggle.checked; }
            document.querySelectorAll('[name=opening_qty]').forEach((el) => el.closest('[class*=col-]').classList.toggle('d-none', toggle.checked));
        };
        toggle?.addEventListener('change', apply);
        if (toggle) apply();
    })();
    @endfeature
    (function () {
        let i = {{ count($packs) }};
        document.getElementById('add-pack')?.addEventListener('click', function () {
            const html = document.getElementById('pack-tpl').innerHTML.replaceAll('__', i++);
            document.querySelector('#packs tbody').insertAdjacentHTML('beforeend', html);
        });
    })();
</script>
@endpush
