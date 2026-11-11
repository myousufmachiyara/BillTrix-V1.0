@extends('layouts.app')
@section('title', 'Profit & Loss')
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <x-date-range :from="$from" :to="$to" />
    <div class="col-auto"><button class="btn btn-sm btn-primary">Show</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button></div>
</form>
@include('reports._header', ['title' => 'Profit & Loss Statement', 'period' => \Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')])
<div class="row"><div class="col-lg-8">
<x-card :body="false">
    <table class="table table-sm mb-0">
        <tr class="table-light"><th colspan="2">Revenue</th></tr>
        @foreach ($income as $r)<tr><td class="ps-4">{{ $r->account->name }}</td><td class="num">{{ money($r->amount, 2, true) }}</td></tr>@endforeach
        <tr class="fw-semibold"><td>Net revenue</td><td class="num">{{ money($revenue, 2, true) }}</td></tr>
        <tr class="table-light"><th colspan="2">Cost of sales</th></tr>
        @foreach ($cogs as $r)<tr><td class="ps-4">{{ $r->account->name }}</td><td class="num">{{ money($r->amount, 2, true) }}</td></tr>@endforeach
        <tr class="fw-semibold border-top"><td>Gross profit @if ($revenue)<span class="text-muted small">({{ round($gross / $revenue * 100, 1) }}%)</span>@endif</td><td class="num">{{ money($gross, 2, true) }}</td></tr>
        <tr class="table-light"><th colspan="2">Operating expenses</th></tr>
        @forelse ($expenses as $r)<tr><td class="ps-4">{{ $r->account->name }}</td><td class="num">{{ money($r->amount, 2, true) }}</td></tr>@empty<tr><td class="ps-4 text-muted" colspan="2">None</td></tr>@endforelse
        <tr class="fw-semibold"><td>Total expenses</td><td class="num">{{ money($expense_total, 2, true) }}</td></tr>
        <tr class="fs-5 fw-bold border-top border-2 {{ $net < 0 ? 'text-danger' : 'text-success' }}"><td>Net {{ $net < 0 ? 'loss' : 'profit' }}</td><td class="num">{{ money($net, 2, true) }}</td></tr>
    </table>
</x-card>
</div></div>
@endsection
