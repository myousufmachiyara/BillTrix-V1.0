@php [$type, $perm, $partyType, $label] = $meta; $badge = ['open' => 'primary', 'partial' => 'warning', 'completed' => 'success', 'converted' => 'info', 'cancelled' => 'secondary']; @endphp
@extends('layouts.app')
@section('title', $label)
@section('header-actions')
    @can($perm.'.create')<a href="{{ route('orders.create', $doc) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('New') }}</a>@endcan
@endsection
@section('content')
<x-card :body="false">
    <div class="card-body pb-0">
        <form class="row g-2 align-items-end filters">
            <x-date-range />
            <div class="col-md-3"><label class="form-label small mb-1">{{ __(ucfirst($partyType)) }}</label><x-party-select :type="$partyType" :party="$party" class="form-select-sm" placeholder="All" /></div>
            <div class="col-auto"><label class="form-label small mb-1">{{ __('Status') }}</label>
                <select name="status" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>
                    @if ($type !== 'quotation')<option value="pending" @selected(request('status') === 'pending')>{{ __('Pending (open + partial)') }}</option>@endif
                    @foreach (\App\Models\Order::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <div class="col-md-2"><label class="form-label small mb-1">{{ __('Number / ref.') }}</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Filter') }}</button></div>
        </form>
    </div>
    <div class="table-responsive mt-3">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __(ucfirst($partyType)) }}</th><th>{{ $type === 'quotation' ? __('Valid until') : __('Due') }}</th><th>{{ __('Ref.') }}</th><th class="num">{{ __('Total') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>
            @forelse ($orders as $o)
                <tr data-href="{{ route('orders.show', [$doc, $o]) }}" style="cursor:pointer">
                    <td class="fw-semibold">{{ $o->number }}</td><td>{{ $o->date->format('d-m-Y') }}</td><td>{{ $o->party->name }}</td>
                    <td class="{{ $o->due_date && $o->due_date->isPast() && $o->isOpen() ? 'text-danger' : '' }}">{{ $o->due_date?->format('d-m-Y') }}</td>
                    <td>{{ $o->reference }}</td><td class="num">{{ money($o->total) }}</td>
                    <td><span class="badge bg-{{ $badge[$o->status] ?? 'secondary' }}">{{ __(\App\Models\Order::STATUSES[$o->status]) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5">{{ __('Nothing here yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($orders->hasPages())<div class="card-body">{{ $orders->links() }}</div>@endif
</x-card>
@endsection
