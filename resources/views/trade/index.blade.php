@php [$type, $perm, $partyType, $label] = $meta; @endphp
@extends('layouts.app')
@section('title', $label)
@section('header-actions')
    @can($perm.'.create')
        <a href="{{ route('invoices.create', $doc) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New</a>
    @endcan
@endsection

@section('content')
<x-card :body="false">
    <div class="card-body pb-0">
        <form class="row g-2 align-items-end filters" method="GET">
            <x-date-range />
            <div class="col-md-3">
                <label class="form-label small mb-1">{{ ucfirst($partyType) }}</label>
                <x-party-select :type="$partyType" :party="$party" class="form-select-sm" placeholder="All" />
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Number / ref.</label>
                <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="unpaid" @selected(request('status') === 'unpaid')>Outstanding</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
                <a href="{{ route('invoices.index', $doc) }}" class="btn btn-sm btn-default">Reset</a>
            </div>
        </form>
    </div>
    <div class="table-responsive mt-3">
        <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
                <tr>
                    <th>Number</th><th>Date</th><th>{{ ucfirst($partyType) }}</th><th>Ref.</th>
                    <th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th><th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($invoices as $inv)
                <tr data-href="{{ route('invoices.show', [$doc, $inv]) }}" style="cursor:pointer">
                    <td class="fw-semibold">{{ $inv->number }}</td>
                    <td>{{ $inv->date->format('d-m-Y') }}</td>
                    <td>{{ $inv->party->is_walk_in && $inv->customer_name ? $inv->customer_name.' (walk-in)' : $inv->party->name }}</td>
                    <td>{{ $inv->reference }}</td>
                    <td class="num">{{ money($inv->total) }}</td>
                    <td class="num">{{ money($inv->paid_amount) }}</td>
                    <td class="num">{{ money($inv->balance) }}</td>
                    <td><x-status :status="$inv->status" /></td>
                    <td class="text-end text-nowrap no-print">
                        <a href="{{ route('invoices.print', [$doc, $inv]) }}" target="_blank" class="text-success me-2" title="Print"><i class="fas fa-print"></i></a>
                        @if (! $inv->isReturn() && in_array($type, ['sale', 'purchase']))
                            @can(($type === 'sale' ? 'sale_returns' : 'purchase_returns').'.create')
                                <a href="{{ route('invoices.create', [$type === 'sale' ? 'sale-returns' : 'purchase-returns', 'from' => $inv->id]) }}" class="text-warning me-2" title="Return"><i class="fas fa-undo"></i></a>
                            @endcan
                        @endif
                        <a href="{{ route('invoices.edit', [$doc, $inv]) }}" class="text-primary" title="Edit"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-5">No {{ strtolower($label) }} found.</td></tr>
            @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4">{{ $sum->n }} document(s)</td>
                    <td class="num">{{ money($sum->total) }}</td>
                    <td class="num">{{ money($sum->total - $sum->due) }}</td>
                    <td class="num">{{ money($sum->due) }}</td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @if ($invoices->hasPages())<div class="card-body">{{ $invoices->links() }}</div>@endif
</x-card>
@endsection
