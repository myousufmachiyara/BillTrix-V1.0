@php $titles = ['receipt' => 'Receipt Vouchers', 'payment' => 'Payment / Expense Vouchers', 'journal' => 'Journal Vouchers', 'contra' => 'Cash ⇄ Bank Transfers']; @endphp
@extends('layouts.app')
@section('title', __($titles[$type]))
@section('header-actions')
    @can('vouchers.create')<a href="{{ route('vouchers.create', $type) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('New') }}</a>@endcan
@endsection
@section('content')
<x-card :body="false">
    <div class="card-body pb-0">
        <form class="row g-2 align-items-end filters">
            <x-date-range />
            <div class="col-md-3"><label class="form-label small mb-1">{{ __('Search') }}</label><input type="search" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="{{ __('Number, narration, ref.') }}"></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Filter') }}</button></div>
        </form>
    </div>
    <div class="table-responsive mt-3">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Party') }}</th><th>{{ __('Narration') }}</th><th class="num">{{ __('Amount') }}</th><th>{{ __('By') }}</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr data-href="{{ route('vouchers.show', [$type, $e]) }}" style="cursor:pointer">
                    <td class="fw-semibold">{{ $e->number }} @if ($e->source_type)<span class="badge bg-light text-dark" title="{{ __('Created from a document') }}">{{ __('auto') }}</span>@endif</td>
                    <td>{{ $e->date->format('d-m-Y') }}</td><td>{{ $e->party?->name }}</td>
                    <td class="text-truncate" style="max-width:320px">{{ $e->narration }}</td>
                    <td class="num">{{ money($e->amount) }}</td><td class="small text-muted">{{ $e->creator?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No vouchers.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($entries->hasPages())<div class="card-body">{{ $entries->links() }}</div>@endif
</x-card>
@endsection
