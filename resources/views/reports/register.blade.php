@php
    $titles = \App\Models\Invoice::TYPES;
    $doc = array_search($type, array_map(fn ($d) => $d[0], \App\Http\Controllers\Trade\InvoiceController::DOCS));
    $partyType = in_array($type, ['sale', 'sale_return']) ? 'customer' : 'supplier';
@endphp
@extends('layouts.app')
@section('title', __($titles[$type]).' '.__('Register'))
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <input type="hidden" name="type" value="{{ $type }}">
    <x-date-range :from="$from" :to="$to" />
    <div class="col-md-3"><label class="form-label small mb-1">{{ __(ucfirst($partyType)) }}</label><x-party-select :type="$partyType" :party="$party" class="form-select-sm" placeholder="All" /></div>
    @if ($branches->count() > 1)
        <div class="col-auto"><label class="form-label small mb-1">{{ __('Branch') }}</label><select name="branch_id" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>@foreach ($branches as $id => $n)<option value="{{ $id }}" @selected((int) request('branch_id') === $id)>{{ $n }}</option>@endforeach</select></div>
    @endif
    <div class="col-auto"><label class="form-label small mb-1">{{ __('Mode') }}</label><select name="mode" class="form-select form-select-sm"><option value="">{{ __('All') }}</option><option value="cash" @selected(request('mode') === 'cash')>{{ __('Cash') }}</option><option value="credit" @selected(request('mode') === 'credit')>{{ __('Credit') }}</option></select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Show') }}</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button> <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-sm btn-default" title="{{ __('Excel') }}"><i class="fas fa-file-excel text-success"></i></a></div>
</form>
@include('reports._header', ['title' => $titles[$type].' Register', 'period' => \Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')])
<x-card :body="false">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Number') }}</th><th>{{ __(ucfirst($partyType)) }}</th><th>{{ __('Mode') }}</th><th class="num">{{ __('Gross') }}</th><th class="num">{{ __('Discount') }}</th><th class="num">{{ __('Tax') }}</th><th class="num">{{ __('Total') }}</th><th class="num">{{ __('Settled') }}</th><th class="num">{{ __('Balance') }}</th>@if ($type === 'sale')@can('reports.profit')<th class="num">{{ __('Profit') }}</th>@endcan @endif</tr></thead>
            <tbody>
            @foreach ($invoices as $i)
                <tr data-href="{{ route('invoices.show', [$doc, $i]) }}" style="cursor:pointer">
                    <td>{{ $i->date->format('d-m-Y') }}</td><td>{{ $i->number }}</td><td>{{ $i->party->name }}</td><td>{{ __(ucfirst($i->payment_mode)) }}</td>
                    <td class="num">{{ money($i->subtotal) }}</td><td class="num">{{ money($i->item_discount + $i->discount) }}</td><td class="num">{{ money($i->tax_total) }}</td>
                    <td class="num fw-semibold">{{ money($i->total) }}</td><td class="num">{{ money($i->paid_amount) }}</td><td class="num">{{ money($i->balance) }}</td>
                    @if ($type === 'sale')@can('reports.profit')<td class="num text-muted">{{ money($i->profit) }}</td>@endcan @endif
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="4">{{ $invoices->count() }} {{ __('documents') }}</td>
                <td class="num">{{ money($invoices->sum('subtotal')) }}</td><td class="num">{{ money($invoices->sum('item_discount') + $invoices->sum('discount')) }}</td>
                <td class="num">{{ money($invoices->sum('tax_total')) }}</td><td class="num">{{ money($invoices->sum('total')) }}</td>
                <td class="num">{{ money($invoices->sum('paid_amount')) }}</td><td class="num">{{ money($invoices->sum('balance')) }}</td>
                @if ($type === 'sale')@can('reports.profit')<td class="num">{{ money($invoices->sum('profit')) }}</td>@endcan @endif</tr></tfoot>
        </table>
    </div>
</x-card>
@endsection
