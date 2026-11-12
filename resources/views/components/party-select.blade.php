@props(['name' => 'party_id', 'type' => null, 'party' => null, 'placeholder' => 'Search name or phone…', 'required' => false])
<select name="{{ $name }}" data-party-select data-type="{{ $type }}" data-placeholder="{{ __($placeholder) }}" {{ $attributes->merge(['class' => 'form-select']) }} @required($required)>
    @if ($party)<option value="{{ $party->id }}" selected>{{ $party->name }}</option>@else<option value=""></option>@endif
</select>
