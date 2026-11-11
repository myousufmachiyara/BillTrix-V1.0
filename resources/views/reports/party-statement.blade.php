@extends('layouts.app')
@section('title', 'Statement of Account')
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <div class="col-md-4"><label class="form-label small mb-1">Customer / supplier</label><x-party-select :party="$party" class="form-select-sm" /></div>
    <x-date-range :from="$from" :to="$to" />
    <div class="col-auto"><button class="btn btn-sm btn-primary">Show</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button></div>
</form>
@if ($data)
    <div class="d-none d-print-block mb-3">
        <div class="d-flex justify-content-between">
            <div><h3 class="mb-0">{{ $business->name }}</h3><div>{{ $business->address }}</div><div>{{ $business->phone }}</div></div>
            <div class="text-end"><h4 class="mb-0">STATEMENT OF ACCOUNT</h4><div>{{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</div></div>
        </div>
        <div class="mt-2"><strong>{{ $party->name }}</strong> {{ $party->address }} {{ $party->city }} {{ $party->phone }}</div>
    </div>
    <div class="row mb-3 no-print">
        @foreach ([['Opening', $data['opening']], ['Debits', $data['debit']], ['Credits', $data['credit']], ['Closing', $data['closing']]] as [$l, $v])
            <div class="col-6 col-md-3"><div class="card stat-card"><div class="card-body py-2"><div class="stat-label">{{ $l }}</div><div class="stat-value fs-5">{{ in_array($l, ['Opening', 'Closing']) ? drcr($v) : money($v) }}</div></div></div></div>
        @endforeach
    </div>
    @include('reports._ledger-table')
    <p class="small text-muted">Dr balance = amount owed to {{ $business->name }}; Cr balance = amount owed by {{ $business->name }}.</p>
@else
    <x-card><p class="text-muted mb-0">Choose a customer or supplier.</p></x-card>
@endif
@endsection
