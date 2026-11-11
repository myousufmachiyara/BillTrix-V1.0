@extends('layouts.app')
@section('title', 'Delivery Challans')
@section('header-actions')
    @can('deliveries.create')<a href="{{ route('deliveries.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New challan</a>@endcan
@endsection
@section('content')
<x-card :body="false">
    <div class="card-body pb-0">
        <form class="row g-2 align-items-end filters">
            <x-date-range />
            <div class="col-md-3"><label class="form-label small mb-1">Customer</label><x-party-select type="customer" :party="$party" class="form-select-sm" placeholder="All" /></div>
            <div class="col-auto"><label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm"><option value="">All</option>
                    <option value="pending" @selected(request('status') === 'pending')>Not billed</option>
                    <option value="invoiced" @selected(request('status') === 'invoiced')>Billed</option></select></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
        </form>
    </div>
    <form method="GET" action="{{ route('invoices.create', 'sales') }}" id="bill-form">
        <input type="hidden" name="deliveries" id="bill-ids">
        <div class="table-responsive mt-3">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th style="width:30px"></th><th>Number</th><th>Date</th><th>Customer</th><th>Order</th><th class="num">Items</th><th>Vehicle</th><th>Invoice</th></tr></thead>
                <tbody>
                @forelse ($deliveries as $d)
                    <tr data-href="{{ route('deliveries.show', $d) }}" style="cursor:pointer">
                        <td>@if (! $d->invoice_id)<input type="checkbox" class="form-check-input pick" value="{{ $d->id }}" data-party="{{ $d->party_id }}">@endif</td>
                        <td class="fw-semibold">{{ $d->number }}</td><td>{{ $d->date->format('d-m-Y') }}</td><td>{{ $d->party->name }}</td>
                        <td>{{ $d->order?->number }}</td><td class="num">{{ $d->items_count }}</td><td>{{ $d->vehicle_no }}</td>
                        <td>{!! $d->invoice ? '<span class="badge bg-success">'.e($d->invoice->number).'</span>' : '<span class="badge bg-warning text-dark">Not billed</span>' !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-5">No challans.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @can('sales.create')
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="small text-muted">Tick challans of one customer to bill them together on one invoice.</span>
                <button type="submit" class="btn btn-primary btn-sm" id="bill-btn" disabled><i class="fas fa-file-invoice"></i> Invoice selected</button>
            </div>
        @endcan
    </form>
    @if ($deliveries->hasPages())<div class="card-body">{{ $deliveries->links() }}</div>@endif
</x-card>
@endsection
@push('scripts')
<script>
    $(document).on('change', '.pick', function () {
        const picked = $('.pick:checked');
        const parties = new Set(picked.map((_, el) => el.dataset.party).get());
        $('#bill-ids').val(picked.map((_, el) => el.value).get().join(','));
        $('#bill-btn').prop('disabled', !picked.length || parties.size > 1).attr('title', parties.size > 1 ? 'Choose challans of one customer' : '');
    });
</script>
@endpush
