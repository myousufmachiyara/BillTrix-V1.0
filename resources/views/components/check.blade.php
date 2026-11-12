@props(['name', 'label', 'checked' => false, 'col' => 'col-md-6', 'help' => null])
@php $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)); @endphp
<div class="{{ $col }} mb-3">
    <div class="form-check form-switch">
        <input type="hidden" name="{{ $name }}" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" id="{{ $id }}" value="1" @checked(old($name, $checked)) {{ $attributes }}>
        <label class="form-check-label" for="{{ $id }}">{{ (is_string($label) ? __($label) : $label) }}</label>
    </div>
    @if ($help)<div class="form-text ms-4 ps-2">{{ (is_string($help) ? __($help) : $help) }}</div>@endif
</div>
