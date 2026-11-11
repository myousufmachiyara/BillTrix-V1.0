@props(['status'])
@php $map = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger']; @endphp
<span class="badge bg-{{ $map[$status] ?? 'secondary' }}">{{ ucfirst($status) }}</span>
