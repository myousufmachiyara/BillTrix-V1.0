@php [$type, $perm, $partyType, $label] = $meta; @endphp
@extends('layouts.app')
@section('title', __($invoice->title).' '.$invoice->number)
@section('header-actions')
    <a href="{{ route('invoices.index', $doc) }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> {{ __($label) }}</a>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-9">
        <x-card>
            <x-slot:actions>
                @can('audit.view')<a href="{{ route('settings.audit', ['type' => 'invoice', 'id' => $invoice->id]) }}" class="btn btn-sm btn-default" title="{{ __('History') }}"><i class="fas fa-history"></i></a>@endcan
                <a href="{{ route('invoices.print', [$doc, $invoice]) }}" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-print"></i> {{ __('Print') }}</a>
                <a href="{{ route('invoices.print', [$doc, $invoice, 'layout' => $business->setting('invoice_layout') === 'thermal' ? 'a4' : 'thermal']) }}" target="_blank" class="btn btn-sm btn-outline-success">{{ $business->setting('invoice_layout') === 'thermal' ? 'A4' : 'Thermal' }}</a>
                @if (in_array($type, ['sale', 'purchase']))
                    @can(($type === 'sale' ? 'sale_returns' : 'purchase_returns').'.create')
                        <a href="{{ route('invoices.create', [$type === 'sale' ? 'sale-returns' : 'purchase-returns', 'from' => $invoice->id]) }}" class="btn btn-sm btn-warning"><i class="fas fa-undo"></i> {{ __('Return') }}</a>
                    @endcan
                @endif
                @if ($invoice->balance > 0 && ! $invoice->party->is_walk_in)
                    @can('vouchers.create')
                        <a href="{{ route('vouchers.create', [in_array($type, ['sale', 'purchase_return']) ? 'receipt' : 'payment', 'party_id' => $invoice->party_id, 'invoice_id' => $invoice->id]) }}" class="btn btn-sm btn-primary"><i class="fas fa-money-bill"></i> {{ in_array($type, ['sale', 'purchase_return']) ? 'Receive' : 'Pay' }}</a>
                    @endcan
                @endif
                @if ($invoice->returns->isEmpty() && $invoice->fbr_status !== 'synced')
                    <a href="{{ route('invoices.edit', [$doc, $invoice]) }}" class="btn btn-sm btn-default"><i class="fas fa-edit"></i> {{ __('Edit') }}</a>
                    @can($perm.'.delete')
                        <x-delete :action="route('invoices.destroy', [$doc, $invoice])" label="Delete" confirm="Delete {{ $invoice->number }}? Stock and accounts will be reversed." />
                    @endcan
                @endif
            </x-slot:actions>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <div class="text-muted small">{{ __(ucfirst($partyType)) }}</div>
                    <div class="fw-semibold"><a href="{{ route('parties.show', $invoice->party) }}">{{ $invoice->party->name }}</a></div>
                    @if ($invoice->customer_name)<div>{{ $invoice->customer_name }} {{ $invoice->customer_phone }}</div>@endif
                    @if ($invoice->order)<div class="mt-1">{{ __('Against order') }} <a href="{{ route('orders.show', [\App\Http\Controllers\Trade\OrderController::docFor($invoice->order->type), $invoice->order]) }}">{{ $invoice->order->number }}</a></div>@endif
                    @if ($invoice->deliveries->isNotEmpty())<div class="mt-1">{{ __('Challans:') }} @foreach ($invoice->deliveries as $d)<a href="{{ route('deliveries.show', $d) }}">{{ $d->number }}</a>@if (! $loop->last), @endif @endforeach</div>@endif
                    @if ($invoice->returnOf)<div class="mt-1">{{ __('Return of') }} <a href="{{ route('invoices.show', [$type === 'sale_return' ? 'sales' : 'purchases', $invoice->returnOf]) }}">{{ $invoice->returnOf->number }}</a></div>@endif
                </div>
                <div class="col-sm-6 text-sm-end">
                    <div><span class="text-muted">{{ __('Date:') }}</span> {{ $invoice->date->format('d M Y') }}</div>
                    @if ($invoice->due_date)<div><span class="text-muted">{{ __('Due:') }}</span> {{ $invoice->due_date->format('d M Y') }}</div>@endif
                    @if ($invoice->reference)<div><span class="text-muted">{{ __('Ref:') }}</span> {{ $invoice->reference }}</div>@endif
                    <div><span class="text-muted">{{ __('Warehouse:') }}</span> {{ $invoice->warehouse->name }}</div>
                    <div><x-status :status="$invoice->status" /></div>
                    @if ($invoice->fbr_status)
                        <div class="mt-1 small">{{ __('FBR:') }}
                            @if ($invoice->fbr_status === 'synced')<span class="badge bg-success">{{ $invoice->fbr_invoice_number }}</span>
                            @else<span class="badge bg-{{ $invoice->fbr_status === 'failed' ? 'danger' : 'warning' }}" title="{{ $invoice->fbr_response }}">{{ $invoice->fbr_status }}</span>
                                <form method="POST" action="{{ route('fbr.retry', $invoice) }}" class="d-inline">@csrf<button class="btn btn-link btn-sm p-0">{{ __('retry') }}</button></form>@endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light"><tr><th>#</th><th>{{ __('Item') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Rate') }}</th><th class="num">{{ __('Disc') }}</th>@feature('tax')<th class="num">{{ __('Tax') }}</th>@endfeature<th class="num">{{ __('Amount') }}</th>
                        @can('reports.profit')@if ($type === 'sale')<th class="num text-muted">{{ __('Cost') }}</th>@endif @endcan</tr></thead>
                    <tbody>
                    @foreach ($invoice->items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->itemName() }}</td>
                            <td class="num">{{ qty($item->quantity) }} {{ $item->unit->short_name }}</td>
                            <td class="num">{{ money($item->price) }}</td>
                            <td class="num">{{ (float) $item->discount_percent ? qty($item->discount_percent).'%' : '' }}</td>
                            @feature('tax')<td class="num">{{ money($item->tax_amount) }}</td>@endfeature
                            <td class="num">{{ money($item->amount) }}</td>
                            @can('reports.profit')@if ($type === 'sale')<td class="num text-muted">{{ money($item->unit_cost * $item->base_quantity) }}</td>@endif @endcan
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if ($invoice->notes)<p class="text-muted mb-0"><strong>{{ __('Notes:') }}</strong> {{ $invoice->notes }}</p>@endif
        </x-card>
    </div>

    <div class="col-lg-3">
        <x-card title="Summary">
            <table class="table table-borderless totals-table mb-0">
                <tr><td>{{ __('Gross') }}</td><td class="num">{{ money($invoice->subtotal) }}</td></tr>
                @if ((float) $invoice->item_discount)<tr><td>{{ __('Item discounts') }}</td><td class="num">-{{ money($invoice->item_discount) }}</td></tr>@endif
                @if ((float) $invoice->tax_total)<tr><td>{{ __('Tax') }}</td><td class="num">{{ money($invoice->tax_total) }}</td></tr>@endif
                @if ((float) $invoice->discount)<tr><td>{{ __('Bill discount') }}</td><td class="num">-{{ money($invoice->discount) }}</td></tr>@endif
                @if ((float) $invoice->other_charges)<tr><td>{{ __('Other charges') }}</td><td class="num">{{ money($invoice->other_charges) }}</td></tr>@endif
                @if ((float) $invoice->round_off)<tr><td>{{ __('Round off') }}</td><td class="num">{{ money($invoice->round_off) }}</td></tr>@endif
                @if ((float) $invoice->wht_amount)<tr><td>{{ __('WHT') }} {{ qty($invoice->wht_rate) }}%</td><td class="num">{{ $invoice->isSaleSide() ? '' : '-' }}{{ money($invoice->wht_amount) }}</td></tr>@endif
                @if ((float) $invoice->service_fee)<tr><td>{{ __('FBR POS fee') }}</td><td class="num">{{ money($invoice->service_fee) }}</td></tr>@endif
                <tr class="grand"><td>{{ __('Total') }}</td><td class="num">{{ money($invoice->total) }}</td></tr>
                <tr><td>{{ __('Settled') }}</td><td class="num">{{ money($invoice->paid_amount) }}</td></tr>
                <tr><td class="fw-bold">{{ __('Balance') }}</td><td class="num fw-bold">{{ money($invoice->balance) }}</td></tr>
                @can('reports.profit')
                    @if ($type === 'sale')<tr><td class="text-muted">{{ __('Gross profit') }}</td><td class="num text-muted">{{ money($invoice->profit) }}</td></tr>@endif
                @endcan
            </table>
        </x-card>

        @if ($invoice->allocations->isNotEmpty())
            <x-card title="Settlements" :body="false">
                <ul class="list-group list-group-flush">
                    @foreach ($invoice->allocations as $a)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $a->entry->number }} <small class="text-muted">{{ $a->entry->date->format('d-m-Y') }}</small></span>
                            <span class="num">{{ money($a->amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        @if ($invoice->returns->isNotEmpty())
            <x-card title="Returns" :body="false">
                <ul class="list-group list-group-flush">
                    @foreach ($invoice->returns as $r)
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="{{ route('invoices.show', [$type === 'sale' ? 'sale-returns' : 'purchase-returns', $r]) }}">{{ $r->number }}</a>
                            <span class="num">{{ money($r->total) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <p class="small text-muted">{{ __('Created by') }} {{ $invoice->creator?->name ?? '—' }} · {{ $invoice->created_at->format('d M Y H:i') }}</p>
    </div>
</div>
@endsection
