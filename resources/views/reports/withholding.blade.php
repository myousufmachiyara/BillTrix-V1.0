@extends('layouts.app')
@section('title', __('Sales Tax & Withholding Tax'))
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <x-date-range :from="$from" :to="$to" />
    <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Show') }}</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-sm btn-default"><i class="fas fa-file-excel"></i></a></div>
</form>
@include('reports._header', ['title' => 'Sales Tax & Withholding Tax', 'period' => \Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')])
<div class="row">
    <div class="col-lg-4">
        <x-card title="Sales tax">
            <table class="table table-sm mb-0">
                <tr><td>{{ __('Output tax (sales − returns)') }}</td><td class="num">{{ money($salesTax['output']) }}</td></tr>
                <tr><td>{{ __('Input tax (purchases − returns)') }}</td><td class="num">{{ money($salesTax['input']) }}</td></tr>
                <tr class="fw-bold border-top"><td>{{ $salesTax['output'] >= $salesTax['input'] ? __('Payable') : __('Carried forward') }}</td><td class="num">{{ money(abs($salesTax['output'] - $salesTax['input'])) }}</td></tr>
            </table>
        </x-card>
    </div>
    <div class="col-lg-8">
        @foreach (['sales' => __('Withholding tax collected on sales').' ('.$business->setting('wht_sales_label').')', 'purchases' => __('Withholding tax deducted on purchases').' ('.$business->setting('wht_purchase_label').')'] as $side => $title)
            @php $rows = $wht[$side] ?? collect(); @endphp
            <x-card :title="$title" :body="false">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>{{ __('Party') }}</th><th>{{ __('NTN / CNIC') }}</th><th>{{ __('Status') }}</th><th class="num">{{ __('Bills') }}</th><th class="num">{{ __('Taxable amount') }}</th><th class="num">{{ __('WHT') }}</th></tr></thead>
                    <tbody>
                    @forelse ($rows as $r)
                        <tr><td>{{ $r->party->name }}</td><td>{{ $r->party->ntn ?: $r->party->cnic }}</td><td>{{ $r->party->is_filer ? __('Filer') : __('Non-filer') }}</td>
                            <td class="num">{{ $r->count }}</td><td class="num">{{ money($r->base) }}</td><td class="num">{{ money($r->wht) }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="text-muted text-center py-3">{{ __('None in this period.') }}</td></tr>
                    @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())<tfoot><tr><td colspan="4">{{ __('Total') }}</td><td class="num">{{ money($rows->sum('base')) }}</td><td class="num">{{ money($rows->sum('wht')) }}</td></tr></tfoot>@endif
                </table>
            </x-card>
        @endforeach
        <x-card :title="__('Withheld on payments and receipts')" :body="false">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Voucher') }}</th><th>{{ __('Party') }}</th><th>{{ __('NTN / CNIC') }}</th><th>{{ __('Type') }}</th><th class="num">%</th><th class="num">{{ __('WHT') }}</th></tr></thead>
                <tbody>
                @forelse ($vouchers as $v)
                    <tr><td>{{ $v->date->format('d-m-Y') }}</td><td><a href="{{ route('vouchers.show', [$v->type, $v]) }}">{{ $v->number }}</a></td><td>{{ $v->party?->name }}</td><td>{{ $v->party?->ntn ?: $v->party?->cnic }}</td>
                        <td>{{ $v->type === 'payment' ? __('Deducted by us (payable to FBR)') : __('Deducted by customer (our tax credit)') }}</td><td class="num">{{ (float) $v->wht_rate ? qty($v->wht_rate) : '' }}</td><td class="num">{{ money($v->wht_amount) }}</td></tr>
                @empty
                    <tr><td colspan="7" class="text-muted text-center py-3">{{ __('None in this period.') }}</td></tr>
                @endforelse
                </tbody>
                @if ($vouchers->isNotEmpty())
                    <tfoot>
                        <tr><td colspan="6">{{ __('Deducted by us') }}</td><td class="num">{{ money($vouchers->where('type', 'payment')->sum('wht_amount')) }}</td></tr>
                        <tr><td colspan="6">{{ __('Deducted by customers') }}</td><td class="num">{{ money($vouchers->where('type', 'receipt')->sum('wht_amount')) }}</td></tr>
                    </tfoot>
                @endif
            </table>
        </x-card>
    </div>
</div>
@endsection
