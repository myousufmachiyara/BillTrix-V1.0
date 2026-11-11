@extends('layouts.app')
@section('title', 'Product-wise Sales & Margin')
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <x-date-range :from="$from" :to="$to" />
    <div class="col-md-3"><label class="form-label small mb-1">Category</label><select name="category_id" class="form-select form-select-sm"><option value="">All</option>@foreach ($categories as $id => $n)<option value="{{ $id }}" @selected((int) request('category_id') === $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Show</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button></div>
</form>
@include('reports._header', ['title' => 'Product-wise Sales & Margin', 'period' => \Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')])
<x-card :body="false">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>Product</th><th class="num">Qty sold (net)</th><th class="num">Revenue</th><th class="num">Cost</th><th class="num">Profit</th><th class="num">Margin</th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            <tr><td>{{ $r->name }}</td><td class="num">{{ qty($r->qty) }} {{ $r->unit }}</td><td class="num">{{ money($r->revenue) }}</td><td class="num">{{ money($r->cost) }}</td>
                <td class="num {{ $r->profit < 0 ? 'text-danger' : '' }}">{{ money($r->profit) }}</td><td class="num">{{ $r->margin }}%</td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No sales in this period.</td></tr>
        @endforelse
        </tbody>
        @php $rev = $rows->sum('revenue'); $pr = $rows->sum('profit'); @endphp
        <tfoot><tr><td colspan="2">Total</td><td class="num">{{ money($rev) }}</td><td class="num">{{ money($rows->sum('cost')) }}</td><td class="num">{{ money($pr) }}</td><td class="num">{{ $rev > 0 ? round($pr / $rev * 100, 1) : 0 }}%</td></tr></tfoot>
    </table>
</x-card>
@endsection
