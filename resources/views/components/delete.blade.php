@props(['action', 'confirm' => 'Delete this record? This cannot be undone.', 'label' => null])
<form action="{{ $action }}" method="POST" class="d-inline" onsubmit="return confirm(@js($confirm))">
    @csrf @method('DELETE')
    <button type="submit" {{ $attributes->merge(['class' => $label ? 'btn btn-outline-danger btn-sm' : 'btn btn-link p-0 text-danger']) }} title="Delete">
        <i class="fas fa-trash-alt"></i>{{ $label ? ' '.$label : '' }}
    </button>
</form>
