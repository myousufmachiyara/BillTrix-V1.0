@php $badge = ['synced' => 'success', 'pending' => 'warning', 'failed' => 'danger']; @endphp
@extends('layouts.app')
@section('title', __('FBR Reporting'))
@section('header-actions')
    @can('fbr.manage')
        <form method="POST" action="{{ route('fbr.retry-all') }}">@csrf<button class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> {{ __('Send all pending') }}</button></form>
    @endcan
@endsection
@section('content')
<div class="row g-3 mb-3">
    @foreach (['synced' => 'Reported', 'pending' => 'Waiting', 'failed' => 'Failed'] as $k => $label)
        <div class="col-md-4"><a href="?status={{ $k }}" class="text-decoration-none"><div class="card stat-card" style="border-left-color: var(--bs-{{ $badge[$k] }})"><div class="card-body"><div class="stat-label">{{ __($label) }}</div><div class="stat-value">{{ number_format($counts[$k] ?? 0) }}</div></div></div></a></div>
    @endforeach
</div>
<x-card :body="false">
    <div class="card-body pb-0 d-flex gap-2 small">
        @foreach (['failed' => 'Failed', 'pending' => 'Waiting', 'synced' => 'Reported', 'all' => 'All'] as $k => $label)
            <a href="?status={{ $k }}" class="btn btn-sm {{ request('status', 'failed') === $k ? 'btn-primary' : 'btn-default' }}">{{ __($label) }}</a>
        @endforeach
    </div>
    <div class="table-responsive mt-3">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th class="num">{{ __('Total') }}</th><th>{{ __('Status') }}</th><th>{{ __('FBR number') }}</th><th>{{ __('Message') }}</th><th class="num">{{ __('Tries') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($invoices as $inv)
                <tr>
                    <td><a href="{{ route('invoices.show', [$inv->type === 'sale' ? 'sales' : 'sale-returns', $inv]) }}">{{ $inv->number }}</a></td>
                    <td>{{ $inv->date->format('d-m-Y') }}</td><td>{{ $inv->party->is_walk_in ? ($inv->customer_name ?: __('Walk-in')) : $inv->party->name }}</td>
                    <td class="num">{{ money($inv->total) }}</td>
                    <td><span class="badge bg-{{ $badge[$inv->fbr_status] ?? 'secondary' }}">{{ $inv->fbr_status }}</span></td>
                    <td class="small">{{ $inv->fbr_invoice_number }}</td>
                    <td class="small text-muted text-truncate" style="max-width:280px" title="{{ $inv->fbr_response }}">{{ $inv->fbr_response }}</td>
                    <td class="num">{{ $inv->fbr_attempts }}</td>
                    <td>@if ($inv->fbr_status !== 'synced')<form method="POST" action="{{ route('fbr.retry', $inv) }}">@csrf<button class="btn btn-link btn-sm p-0">{{ __('Retry') }}</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">{{ __('Nothing here.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($invoices->hasPages())<div class="card-body">{{ $invoices->links() }}</div>@endif
</x-card>
<p class="small text-muted">{{ __('Bills that couldn\'t reach FBR are retried every 5 minutes. A reported bill can\'t be edited or deleted — record a return instead.') }}</p>
@endsection
