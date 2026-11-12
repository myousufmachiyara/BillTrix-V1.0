@props(['name' => 'product_id', 'product' => null, 'placeholder' => 'Search product…'])
<select name="{{ $name }}" data-product-select data-placeholder="{{ __($placeholder) }}" {{ $attributes->merge(['class' => 'form-select']) }}>
    @if ($product)<option value="{{ $product->id }}" selected>{{ $product->name }}</option>@else<option value=""></option>@endif
</select>
