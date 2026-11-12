@extends('layouts.app')
@section('title', __(ucfirst($adj->type)).' '.$adj->number)
@section('content')
<x-card>
    <x-slot:actions>
        <button onclick="window.print()" class="btn btn-sm btn-success no-print"><i class="fas fa-print"></i> {{ __('Print') }}</button>
        @can('stock.adjust')<x-delete :action="route('stock-adjustments.destroy', $adj)" label="Delete & reverse" confirm="Delete this document and reverse its stock and accounting effect?" />@endcan
    </x-slot:actions>
    <p>
        <strong>{{ __('Date:') }}</strong> {{ $adj->date->format('d M Y') }} · <strong>{{ __('Warehouse:') }}</strong> {{ $adj->warehouse->name }}
        @if ($adj->reason) · <strong>{{ __('Reason:') }}</strong> {{ \App\Models\StockAdjustment::REASONS[$adj->reason] ?? $adj->reason }}@endif
        · <strong>{{ __('By:') }}</strong> {{ $adj->creator?->name }}
    </p>
    @if ($adj->notes)<p class="text-muted">{{ $adj->notes }}</p>@endif
    <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>{{ __('Product') }}</th>@if ($adj->type === 'count')<th class="num">{{ __('System') }}</th>@endif<th class="num">{{ __('Change') }}</th><th class="num">{{ __('Unit cost') }}</th><th class="num">{{ __('Value') }}</th></tr></thead>
        <tbody>
        @foreach ($adj->items as $i)
            <tr><td>{{ $i->itemName() }}</td>
                @if ($adj->type === 'count')<td class="num">{{ qty($i->system_quantity) }}</td>@endif
                <td class="num {{ $i->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ qty($i->quantity) }} {{ $i->product->unit->short_name }}</td>
                <td class="num">{{ money($i->unit_cost) }}</td><td class="num">{{ money($i->quantity * $i->unit_cost) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="{{ $adj->type === 'count' ? 4 : 3 }}">{{ __('Net value') }}</td><td class="num">{{ money($adj->total_value) }}</td></tr></tfoot>
    </table>
</x-card>
@endsection
