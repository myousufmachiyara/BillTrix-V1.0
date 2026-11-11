@php
    [$type, $perm, $partyType, $label] = $meta;
    $badge = ['open' => 'primary', 'partial' => 'warning', 'completed' => 'success', 'converted' => 'info', 'cancelled' => 'secondary'];
    $open = $order->isOpen();
@endphp
@extends('layouts.app')
@section('title', $order->title.' '.$order->number)
@section('header-actions')
    <a href="{{ route('orders.index', $doc) }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> {{ $label }}</a>
@endsection
@section('content')
<div class="row">
    <div class="col-lg-9">
        <x-card>
            <x-slot:actions>
                <a href="{{ route('orders.print', [$doc, $order]) }}" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-print"></i> Print</a>
                @if ($type === 'quotation' && $open)
                    @feature('sales_orders')@can('sales_orders.create')<a href="{{ route('orders.create', ['sales-orders', 'quotation' => $order->id]) }}" class="btn btn-sm btn-primary"><i class="fas fa-file-signature"></i> Make sales order</a>@endcan @endfeature
                    @can('sales.create')<a href="{{ route('invoices.create', ['sales', 'quotation' => $order->id]) }}" class="btn btn-sm btn-outline-primary">Invoice directly</a>@endcan
                @endif
                @if ($type === 'sale_order' && $open)
                    @feature('delivery_challans')@can('deliveries.create')<a href="{{ route('deliveries.create', ['order' => $order->id]) }}" class="btn btn-sm btn-primary"><i class="fas fa-truck"></i> Deliver</a>@endcan @endfeature
                    @can('sales.create')<a href="{{ route('invoices.create', ['sales', 'order' => $order->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-invoice"></i> Invoice pending</a>@endcan
                @endif
                @if ($type === 'purchase_order' && $open)
                    @can('purchases.create')<a href="{{ route('invoices.create', ['purchases', 'order' => $order->id]) }}" class="btn btn-sm btn-primary"><i class="fas fa-truck-loading"></i> Receive / bill</a>@endcan
                @endif
                @can($perm.'.edit')
                    @if ($open)<a href="{{ route('orders.edit', [$doc, $order]) }}" class="btn btn-sm btn-default"><i class="fas fa-edit"></i> Edit</a>@endif
                    @if (in_array($order->status, ['open', 'partial', 'cancelled']))
                        <form method="POST" action="{{ route('orders.cancel', [$doc, $order]) }}" class="d-inline" onsubmit="return confirm('{{ $order->status === 'cancelled' ? 'Re-open' : 'Cancel' }} {{ $order->number }}?')">
                            @csrf @method('PUT')
                            @if ($order->status === 'cancelled')<input type="hidden" name="reopen" value="1">@endif
                            <button class="btn btn-sm btn-outline-secondary">{{ $order->status === 'cancelled' ? 'Re-open' : 'Cancel' }}</button>
                        </form>
                    @endif
                @endcan
                @can($perm.'.delete')<x-delete :action="route('orders.destroy', [$doc, $order])" label="Delete" />@endcan
            </x-slot:actions>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <div class="text-muted small">{{ ucfirst($partyType) }}</div>
                    <div class="fw-semibold"><a href="{{ route('parties.show', $order->party) }}">{{ $order->party->name }}</a></div>
                    @if ($order->quotation)<div class="small">From quotation <a href="{{ route('orders.show', ['quotations', $order->quotation]) }}">{{ $order->quotation->number }}</a></div>@endif
                </div>
                <div class="col-sm-6 text-sm-end">
                    <div><span class="text-muted">Date:</span> {{ $order->date->format('d M Y') }}</div>
                    @if ($order->due_date)<div><span class="text-muted">{{ $type === 'quotation' ? 'Valid until' : 'Due' }}:</span> {{ $order->due_date->format('d M Y') }}</div>@endif
                    @if ($order->reference)<div><span class="text-muted">Ref:</span> {{ $order->reference }}</div>@endif
                    <span class="badge bg-{{ $badge[$order->status] }}">{{ \App\Models\Order::STATUSES[$order->status] }}</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light"><tr><th>#</th><th>Item</th><th class="num">Ordered</th>
                        @if ($type !== 'quotation')<th class="num">{{ $type === 'purchase_order' ? 'Received' : 'Delivered / billed' }}</th><th class="num">Pending</th>@endif
                        <th class="num">Rate</th><th class="num">Disc</th><th class="num">Amount</th></tr></thead>
                    <tbody>
                    @foreach ($order->items as $i)
                        <tr>
                            <td>{{ $loop->iteration }}</td><td>{{ $i->product->name }}</td>
                            <td class="num">{{ qty($i->quantity) }} {{ $i->unit->short_name }}</td>
                            @if ($type !== 'quotation')
                                <td class="num">{{ qty((float) $i->fulfilled_quantity / max((float) $i->factor, 0.0001)) }}</td>
                                <td class="num {{ $i->pending > 0 && $open ? 'fw-bold text-warning' : 'text-muted' }}">{{ qty($i->pending) }}</td>
                            @endif
                            <td class="num">{{ money($i->price) }}</td><td class="num">{{ (float) $i->discount_percent ? qty($i->discount_percent).'%' : '' }}</td><td class="num">{{ money($i->amount) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($order->notes)<p class="mb-1"><strong>Notes:</strong> {{ $order->notes }}</p>@endif
            @if ($order->terms)<p class="mb-0 small" style="white-space:pre-line"><strong>Terms:</strong> {{ $order->terms }}</p>@endif
        </x-card>
    </div>
    <div class="col-lg-3">
        <x-card title="Summary">
            <table class="table table-borderless totals-table mb-0">
                <tr><td>Gross</td><td class="num">{{ money($order->subtotal) }}</td></tr>
                @if ((float) $order->item_discount)<tr><td>Item discounts</td><td class="num">-{{ money($order->item_discount) }}</td></tr>@endif
                @if ((float) $order->tax_total)<tr><td>Tax</td><td class="num">{{ money($order->tax_total) }}</td></tr>@endif
                @if ((float) $order->discount)<tr><td>Discount</td><td class="num">-{{ money($order->discount) }}</td></tr>@endif
                @if ((float) $order->other_charges)<tr><td>Other</td><td class="num">{{ money($order->other_charges) }}</td></tr>@endif
                <tr class="grand"><td>Total</td><td class="num">{{ money($order->total) }}</td></tr>
            </table>
        </x-card>
        @if ($order->deliveries->isNotEmpty())
            <x-card title="Delivery challans" :body="false">
                <ul class="list-group list-group-flush">
                    @foreach ($order->deliveries as $d)
                        <li class="list-group-item d-flex justify-content-between"><a href="{{ route('deliveries.show', $d) }}">{{ $d->number }}</a>
                            <span class="small">{{ $d->date->format('d-m') }} · {!! $d->invoice ? '<span class="text-success">'.e($d->invoice->number).'</span>' : '<span class="text-warning">not billed</span>' !!}</span></li>
                    @endforeach
                </ul>
            </x-card>
        @endif
        @if ($order->invoices->isNotEmpty())
            <x-card title="Invoices" :body="false">
                <ul class="list-group list-group-flush">
                    @foreach ($order->invoices as $inv)
                        <li class="list-group-item d-flex justify-content-between"><a href="{{ route('invoices.show', [$type === 'purchase_order' ? 'purchases' : 'sales', $inv]) }}">{{ $inv->number }}</a><span class="num">{{ money($inv->total) }}</span></li>
                    @endforeach
                </ul>
            </x-card>
        @endif
        @if ($children->isNotEmpty())
            <x-card title="Orders from this quotation" :body="false">
                <ul class="list-group list-group-flush">@foreach ($children as $c)<li class="list-group-item"><a href="{{ route('orders.show', ['sales-orders', $c]) }}">{{ $c->number }}</a></li>@endforeach</ul>
            </x-card>
        @endif
        <p class="small text-muted">Created by {{ $order->creator?->name ?? '—' }} · {{ $order->created_at->format('d M Y H:i') }}</p>
    </div>
</div>
@endsection
