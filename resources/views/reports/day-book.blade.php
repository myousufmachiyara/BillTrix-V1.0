@extends('layouts.app')
@section('title', 'Day Book')
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <x-date-range :from="$from" :to="$to" />
    <div class="col-auto"><label class="form-label small mb-1">Type</label>
        <select name="type" class="form-select form-select-sm"><option value="">All</option>
            @foreach (['invoice' => 'Invoices', 'receipt' => 'Receipts', 'payment' => 'Payments', 'journal' => 'Journals', 'contra' => 'Contra', 'stock' => 'Stock', 'opening' => 'Opening'] as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $v }}</option>@endforeach
        </select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Show</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button></div>
</form>
@include('reports._header', ['title' => 'Day Book', 'period' => \Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')])
<x-card :body="false">
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Entry</th><th>Account</th><th>Party</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
        @forelse ($entries as $e)
            <tbody class="border-bottom">
                <tr class="table-light"><td>{{ $e->date->format('d-m-Y') }}</td><td colspan="5"><strong>{{ $e->number }}</strong> <span class="text-muted">{{ $e->narration }}</span></td></tr>
                @foreach ($e->lines as $l)
                    <tr><td></td><td></td><td>{{ $l->account->name }}</td><td>{{ $l->party?->name }}</td><td class="num">{{ (float) $l->debit ? money($l->debit) : '' }}</td><td class="num">{{ (float) $l->credit ? money($l->credit) : '' }}</td></tr>
                @endforeach
            </tbody>
        @empty
            <tbody><tr><td colspan="6" class="text-center text-muted py-4">No entries.</td></tr></tbody>
        @endforelse
    </table>
    @if ($entries->hasPages())<div class="card-body">{{ $entries->links() }}</div>@endif
</x-card>
@endsection
