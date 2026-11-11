@php $titles = ['receipt' => 'Receipt Voucher', 'payment' => 'Payment Voucher', 'journal' => 'Journal Voucher', 'contra' => 'Transfer Voucher']; @endphp
@extends('layouts.app')
@section('title', $titles[$type].' '.$entry->number)
@section('header-actions')
    <a href="{{ route('vouchers.index', $type) }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> All</a>
@endsection
@section('content')
<div class="row"><div class="col-lg-9">
<x-card>
    <x-slot:actions>
        <button onclick="window.print()" class="btn btn-sm btn-success no-print"><i class="fas fa-print"></i> Print</button>
        @if ($entry->isVoucher())
            @can('vouchers.edit')<a href="{{ route('vouchers.edit', [$type, $entry]) }}" class="btn btn-sm btn-default no-print"><i class="fas fa-edit"></i> Edit</a>@endcan
            @can('vouchers.delete')<x-delete :action="route('vouchers.destroy', [$type, $entry])" label="Delete" class="no-print btn btn-outline-danger btn-sm" />@endcan
        @elseif ($entry->source instanceof \App\Models\Invoice)
            @php $doc = array_search($entry->source->type, array_map(fn ($d) => $d[0], \App\Http\Controllers\Trade\InvoiceController::DOCS)); @endphp
            <a href="{{ route('invoices.show', [$doc, $entry->source]) }}" class="btn btn-sm btn-default no-print">Open {{ $entry->source->number }}</a>
        @endif
    </x-slot:actions>
    <div class="d-flex justify-content-between mb-3">
        <div>
            <div class="fs-5 fw-semibold">{{ $business->name }}</div>
            <div class="text-muted">{{ $titles[$type] }} · {{ $entry->number }}</div>
        </div>
        <div class="text-end">
            <div>Date: {{ $entry->date->format('d M Y') }}</div>
            @if ($entry->reference)<div>Ref: {{ $entry->reference }}</div>@endif
            @if ($entry->branch)<div class="small text-muted">{{ $entry->branch->name }}</div>@endif
        </div>
    </div>
    <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Account</th><th>Party</th><th>Memo</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
        <tbody>
        @foreach ($entry->lines as $l)
            <tr><td>{{ $l->account->code }} — {{ $l->account->name }}</td><td>{{ $l->party?->name }}</td><td>{{ $l->memo }}</td>
                <td class="num">{{ (float) $l->debit ? money($l->debit) : '' }}</td><td class="num">{{ (float) $l->credit ? money($l->credit) : '' }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="3">{{ in_words($entry->amount) }}</td><td class="num">{{ money($entry->lines->sum('debit')) }}</td><td class="num">{{ money($entry->lines->sum('credit')) }}</td></tr></tfoot>
    </table>
    @if ($entry->narration)<p><strong>Narration:</strong> {{ $entry->narration }}</p>@endif
    @if ($entry->allocations->isNotEmpty())
        <p class="mb-1"><strong>Applied to:</strong></p>
        <ul>@foreach ($entry->allocations as $a)<li>{{ $a->invoice->number }} — {{ money($a->amount) }}</li>@endforeach</ul>
    @endif
    <div class="d-flex justify-content-between mt-5 pt-3">
        <div class="border-top px-4 pt-1">Prepared by {{ $entry->creator?->name }}</div>
        <div class="border-top px-4 pt-1">Approved by</div>
        <div class="border-top px-4 pt-1">Received by</div>
    </div>
</x-card>
</div></div>
@endsection
