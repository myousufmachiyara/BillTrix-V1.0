@extends('layouts.app')
@section('title', __('Expiry Report'))
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <div class="col-auto"><label class="form-label small mb-1">{{ __('Expiring within (days)') }}</label><input type="number" name="days" min="1" value="{{ $days }}" class="form-control form-control-sm"></div>
    @if ($warehouses->count() > 1)
        <div class="col-auto"><label class="form-label small mb-1">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>@foreach ($warehouses as $id => $n)<option value="{{ $id }}" @selected((int) request('warehouse_id') === $id)>{{ $n }}</option>@endforeach</select></div>
    @endif
    <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Show') }}</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button> <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-sm btn-default" title="{{ __('Excel') }}"><i class="fas fa-file-excel text-success"></i></a></div>
</form>
@include('reports._header', ['title' => 'Expiry Report', 'period' => 'Expired or expiring by '.now()->addDays($days)->format('d M Y')])
<x-card :body="false">
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>{{ __('Product') }}</th><th>{{ __('Batch') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('Status') }}</th><th>{{ __('Warehouse') }}</th><th class="num">{{ __('Quantity') }}</th><th class="num">{{ __('Value') }}</th><th class="no-print"></th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            @php $exp = \Carbon\Carbon::parse($r->expiry_date); $expired = $exp->lt(today()); @endphp
            <tr class="{{ $expired ? 'table-danger' : '' }}">
                <td><a href="{{ route('products.show', $r->product_id) }}">{{ $r->name }}</a></td><td>{{ $r->batch_no }}</td><td>{{ $exp->format('d-m-Y') }}</td>
                <td>{{ $expired ? __('Expired').' '.$exp->diffForHumans() : 'In '.today()->diffInDays($exp).' days' }}</td><td>{{ $r->warehouse }}</td>
                <td class="num">{{ qty($r->quantity) }}</td><td class="num">{{ money($r->quantity * $r->avg_cost) }}</td>
                <td class="no-print text-end">
                    @if ($expired)
                        @can('stock.adjust')
                            <form method="POST" action="{{ route('stock-adjustments.store') }}" onsubmit="return confirm(@js(__('Write off :qty of batch :batch as expired?', ['qty' => qty($r->quantity), 'batch' => $r->batch_no])))">
                                @csrf
                                <input type="hidden" name="type" value="adjustment"><input type="hidden" name="reason" value="expired">
                                <input type="hidden" name="warehouse_id" value="{{ $r->warehouse_id }}"><input type="hidden" name="date" value="{{ now()->toDateString() }}">
                                <input type="hidden" name="items[0][product_id]" value="{{ $r->product_id }}"><input type="hidden" name="items[0][quantity]" value="{{ -$r->quantity }}">
                                <input type="hidden" name="items[0][batch_no]" value="{{ $r->batch_no }}">
                                <button class="btn btn-sm btn-outline-danger">{{ __('Write off') }}</button>
                            </form>
                        @endcan
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">{{ __('Nothing expired or expiring in this window.') }}</td></tr>
        @endforelse
        </tbody>
        @if ($rows->isNotEmpty())<tfoot><tr><td colspan="5">{{ __('Total') }}</td><td class="num">{{ qty($rows->sum('quantity')) }}</td><td class="num">{{ money($rows->sum(fn ($r) => $r->quantity * $r->avg_cost)) }}</td><td class="no-print"></td></tr></tfoot>@endif
    </table>
</x-card>
@endsection
