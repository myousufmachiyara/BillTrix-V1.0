@extends('layouts.app')
@section('title', 'Trial Balance')
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <div class="col-auto"><label class="form-label small mb-1">As of</label><input type="date" name="as_of" value="{{ $asOf }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Show</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button></div>
</form>
@include('reports._header', ['title' => 'Trial Balance', 'period' => 'As of '.\Carbon\Carbon::parse($asOf)->format('d M Y')])
<x-card :body="false">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>Code</th><th>Account</th><th>Type</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
        <tbody>
        @foreach ($rows as $r)
            <tr><td>{{ $r->account->code }}</td><td><a href="{{ route('reports.ledger', ['account_id' => $r->account->id, 'to' => $asOf]) }}" class="text-reset">{{ $r->account->name }}</a></td>
                <td class="small text-muted">{{ \App\Models\Account::TYPES[$r->account->type] }}</td>
                <td class="num">{{ $r->debit ? money($r->debit) : '' }}</td><td class="num">{{ $r->credit ? money($r->credit) : '' }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">Total @if (abs($debit - $credit) > 0.009)<span class="badge bg-danger">Out of balance</span>@endif</td><td class="num">{{ money($debit) }}</td><td class="num">{{ money($credit) }}</td></tr></tfoot>
    </table>
</x-card>
@endsection
