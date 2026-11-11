@props(['name', 'label' => null, 'options' => [], 'value' => null, 'col' => 'col-md-6', 'placeholder' => null, 'help' => null])
@php $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)); $selected = (string) old($name, $value); @endphp
<div class="{{ $col }} mb-3">
    @if ($label)<label for="{{ $id }}" class="form-label">{{ $label }}@if ($attributes->has('required'))<span class="text-danger"> *</span>@endif</label>@endif
    <select name="{{ $name }}" id="{{ $id }}" {{ $attributes->merge(['class' => 'form-select'.($errors->has($name) ? ' is-invalid' : '')]) }}>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected($selected === (string) $key)>{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ $help }}</div>@endif
</div>
