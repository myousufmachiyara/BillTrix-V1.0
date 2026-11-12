@extends('layouts.app')
@section('title', __('Balance Sheet'))
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <div class="col-auto"><label class="form-label small mb-1">{{ __('As of') }}</label><input type="date" name="as_of" value="{{ $asOf }}" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Show') }}</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button></div>
</form>
@include('reports._header', ['title' => 'Balance Sheet', 'period' => 'As of '.\Carbon\Carbon::parse($asOf)->format('d M Y')])
<div class="row">
    <div class="col-lg-6">
        <x-card title="Assets" :body="false">
            <table class="table table-sm mb-0">
                @foreach ($assets->groupBy('group') as $group => $rows)
                    <tr class="table-light"><th colspan="2">{{ $group }}</th></tr>
                    @foreach ($rows as $r)<tr><td class="ps-4">{{ $r->account->name }}</td><td class="num">{{ money($r->amount, 2, true) }}</td></tr>@endforeach
                @endforeach
                <tr class="fw-bold border-top border-2"><td>{{ __('Total assets') }}</td><td class="num">{{ money($asset_total, 2, true) }}</td></tr>
            </table>
        </x-card>
    </div>
    <div class="col-lg-6">
        <x-card title="Liabilities & equity" :body="false">
            <table class="table table-sm mb-0">
                @foreach ($liabilities->groupBy('group') as $group => $rows)
                    <tr class="table-light"><th colspan="2">{{ $group }}</th></tr>
                    @foreach ($rows as $r)<tr><td class="ps-4">{{ $r->account->name }}</td><td class="num">{{ money($r->amount, 2, true) }}</td></tr>@endforeach
                @endforeach
                <tr class="fw-semibold"><td>{{ __('Total liabilities') }}</td><td class="num">{{ money($liability_total, 2, true) }}</td></tr>
                <tr class="table-light"><th colspan="2">{{ __('Equity') }}</th></tr>
                @foreach ($equity as $r)<tr><td class="ps-4">{{ $r->account->name }}</td><td class="num">{{ money($r->amount, 2, true) }}</td></tr>@endforeach
                <tr><td class="ps-4">{{ __('Current profit / (loss)') }}</td><td class="num">{{ money($profit, 2, true) }}</td></tr>
                <tr class="fw-semibold"><td>{{ __('Total equity') }}</td><td class="num">{{ money($equity_total, 2, true) }}</td></tr>
                <tr class="fw-bold border-top border-2"><td>{{ __('Total liabilities & equity') }}</td><td class="num">{{ money($liability_total + $equity_total, 2, true) }}</td></tr>
            </table>
        </x-card>
        @if (abs($asset_total - $liability_total - $equity_total) > 0.009)<div class="alert alert-danger">{{ __('The balance sheet does not balance. Please contact support.') }}</div>@endif
    </div>
</div>
@endsection
