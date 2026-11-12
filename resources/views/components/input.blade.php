@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'col' => 'col-md-6', 'help' => null])
@php $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name)); @endphp
<div class="{{ $col }} mb-3">
    @if ($label)<label for="{{ $id }}" class="form-label">{{ (is_string($label) ? __($label) : $label) }}@if ($attributes->has('required'))<span class="text-danger"> *</span>@endif</label>@endif
    @if ($type === 'textarea')
        <textarea name="{{ $name }}" id="{{ $id }}" {{ $attributes->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : ''), 'rows' => 2]) }}>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" {{ $attributes->merge(['class' => 'form-control'.($errors->has($name) ? ' is-invalid' : '')]) }}>
    @endif
    @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if ($help)<div class="form-text">{{ (is_string($help) ? __($help) : $help) }}</div>@endif
</div>
