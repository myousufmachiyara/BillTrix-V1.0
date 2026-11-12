@extends('layouts.app')
@section('title', __('Stock Adjustments'))
@section('header-actions')
    @can('stock.adjust')
        <a href="{{ route('stock-adjustments.create', ['type' => 'opening']) }}" class="btn btn-default btn-sm">{{ __('Opening stock') }}</a>
        <a href="{{ route('stock-adjustments.create', ['type' => 'count']) }}" class="btn btn-default btn-sm">{{ __('Physical count') }}</a>
        <a href="{{ route('stock-adjustments.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('Adjustment') }}</a>
    @endcan
@endsection
@section('content')
<x-card :body="false">
    <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Warehouse') }}</th><th class="num">{{ __('Items') }}</th><th class="num">{{ __('Value') }}</th><th>{{ __('By') }}</th></tr></thead>
        <tbody>
        @forelse ($adjustments as $a)
            <tr data-href="{{ route('stock-adjustments.show', $a) }}" style="cursor:pointer">
                <td class="fw-semibold">{{ $a->number }}</td><td>{{ $a->date->format('d-m-Y') }}</td><td>{{ __(ucfirst($a->type)) }}</td>
                <td>{{ \App\Models\StockAdjustment::REASONS[$a->reason] ?? '' }}</td><td>{{ $a->warehouse->name }}</td>
                <td class="num">{{ $a->items_count }}</td><td class="num {{ $a->total_value < 0 ? 'text-danger' : '' }}">{{ money($a->total_value) }}</td><td class="small text-muted">{{ $a->creator?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-5">{{ __('No adjustments yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    @if ($adjustments->hasPages())<div class="card-body">{{ $adjustments->links() }}</div>@endif
</x-card>
@endsection
