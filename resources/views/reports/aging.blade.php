@extends('layouts.app')
@section('title', $side === 'receivable' ? 'Receivables Aging' : 'Payables Aging')
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <input type="hidden" name="side" value="{{ $side }}">
    <div class="col-auto"><label class="form-label small mb-1">As of</label><input type="date" name="as_of" value="{{ $asOf }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Show</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button></div>
</form>
@include('reports._header', ['title' => $side === 'receivable' ? 'Receivables Aging' : 'Payables Aging', 'period' => 'As of '.\Carbon\Carbon::parse($asOf)->format('d M Y')])
<x-card :body="false">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>{{ $side === 'receivable' ? 'Customer' : 'Supplier' }}</th><th class="num">Bills</th><th class="num">0–15 days</th><th class="num">16–30</th><th class="num">31–60</th><th class="num">61–90</th><th class="num">90+</th><th class="num">Open bills</th><th class="num">Ledger balance</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr data-href="{{ route('reports.party-statement', ['party_id' => $r->party->id]) }}" style="cursor:pointer">
                    <td>{{ $r->party->name }} <span class="small text-muted">{{ $r->party->phone }}</span></td><td class="num">{{ $r->count }}</td>
                    <td class="num">{{ $r->current ? money($r->current) : '' }}</td><td class="num">{{ $r->d30 ? money($r->d30) : '' }}</td>
                    <td class="num">{{ $r->d60 ? money($r->d60) : '' }}</td><td class="num text-warning">{{ $r->d90 ? money($r->d90) : '' }}</td>
                    <td class="num text-danger fw-semibold">{{ $r->over ? money($r->over) : '' }}</td>
                    <td class="num fw-semibold">{{ money($r->total) }}</td><td class="num text-muted">{{ drcr($r->ledger) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Nothing outstanding.</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr><td>Total</td><td class="num">{{ $rows->sum('count') }}</td>
                @foreach (['current', 'd30', 'd60', 'd90', 'over', 'total'] as $k)<td class="num">{{ money($rows->sum($k)) }}</td>@endforeach<td></td></tr></tfoot>
        </table>
    </div>
</x-card>
<p class="small text-muted">Aging is by invoice date. "Ledger balance" also includes opening balances and advances not applied to a bill.</p>
@endsection
