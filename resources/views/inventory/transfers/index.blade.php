@extends('layouts.app')
@section('title', __('Stock Transfers'))
@section('header-actions')
    @can('stock.transfer')<a href="{{ route('stock-transfers.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('New transfer') }}</a>@endcan
@endsection
@section('content')
<x-card :body="false">
    <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th class="num">{{ __('Items') }}</th></tr></thead>
        <tbody>
        @forelse ($transfers as $t)
            <tr data-href="{{ route('stock-transfers.show', $t) }}" style="cursor:pointer"><td class="fw-semibold">{{ $t->number }}</td><td>{{ $t->date->format('d-m-Y') }}</td><td>{{ $t->fromWarehouse->name }}</td><td>{{ $t->toWarehouse->name }}</td><td class="num">{{ $t->items_count }}</td></tr>
        @empty
            <tr><td colspan="5" class="text-center text-muted py-5">{{ __('No transfers yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
    @if ($transfers->hasPages())<div class="card-body">{{ $transfers->links() }}</div>@endif
</x-card>
@endsection
