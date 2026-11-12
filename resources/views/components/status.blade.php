@props(['status'])
@php $map = ['paid' => 'success', 'partial' => 'warning', 'unpaid' => 'danger']; @endphp
<span class="badge bg-{{ $map[$status] ?? 'secondary' }}">{{ __(ucfirst($status)) }}</span>
