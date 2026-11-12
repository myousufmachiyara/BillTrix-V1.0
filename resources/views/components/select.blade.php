@props(['name', 'label' => null, 'options' => [], 'value' => null, 'col' => 'col-md-6', 'placeholder' => null, 'help' => null])
@php $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)); $selected = (string) old($name, $value); @endphp
<div class="{{ $col }} mb-3">
    @if ($label)<label for="{{ $id }}" class="form-label">{{ (is_string($label) ? __($label) : $label) }}@if ($attributes->has('required'))<span class="text-danger"> *</span>@endif</label>@endif
    <select name="{{ $name }}" id="{{ $id }}" {{ $attributes->merge(['class' => 'form-select'.($errors->has($name) ? ' is-invalid' : '')]) }}>
        @if ($placeholder !== null)<option value="">{{ (is_string($placeholder) ? __($placeholder) : $placeholder) }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected($selected === (string) $key)>{{ (is_string($text) ? __($text) : $text) }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ (is_string($help) ? __($help) : $help) }}</div>@endif
</div>
