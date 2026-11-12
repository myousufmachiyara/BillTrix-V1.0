@extends('layouts.app')
@section('title', $party->name)
@section('header-actions')
    <a href="{{ route('parties.index', ['type' => $party->type === 'supplier' ? 'supplier' : 'customer']) }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> {{ __('All') }}</a>
@endsection
@section('content')
<div class="row">
    <div class="col-lg-4">
        <x-card>
            <x-slot:actions>
                @can('audit.view')<a href="{{ route('settings.audit', ['type' => 'party', 'id' => $party->id]) }}" class="btn btn-sm btn-default" title="{{ __('History') }}"><i class="fas fa-history"></i></a>@endcan
                @can('parties.edit')<a href="{{ route('parties.edit', $party) }}" class="btn btn-sm btn-default"><i class="fas fa-edit"></i> {{ __('Edit') }}</a>@endcan
            </x-slot:actions>
            <div class="text-center mb-3">
                <div class="text-muted small">{{ __('Balance') }}</div>
                <div class="fs-3 fw-bold {{ $balance > 0 ? 'text-dr' : ($balance < 0 ? 'text-cr' : '') }}">{{ drcr($balance) }}</div>
                <div class="small text-muted">{{ $balance > 0 ? 'owes you' : ($balance < 0 ? 'you owe' : 'settled') }}</div>
            </div>
            <dl class="row small mb-0">
                <dt class="col-5">{{ __('Code') }}</dt><dd class="col-7">{{ $party->code }}</dd>
                <dt class="col-5">{{ __('Type') }}</dt><dd class="col-7">{{ __(ucfirst($party->type)) }}</dd>
                @if ($party->phone)<dt class="col-5">{{ __('Phone') }}</dt><dd class="col-7">{{ $party->phone }}</dd>@endif
                @if ($party->address || $party->city)<dt class="col-5">{{ __('Address') }}</dt><dd class="col-7">{{ $party->address }} {{ $party->city }}</dd>@endif
                @if ($party->ntn)<dt class="col-5">{{ __('NTN') }}</dt><dd class="col-7">{{ $party->ntn }}</dd>@endif
                @if ((float) $party->credit_limit)<dt class="col-5">{{ __('Credit limit') }}</dt><dd class="col-7">{{ money($party->credit_limit) }} ({{ $party->credit_days }} {{ __('days)') }}</dd>@endif
                @if ($party->priceLevel)<dt class="col-5">{{ __('Price level') }}</dt><dd class="col-7">{{ $party->priceLevel->name }}</dd>@endif
            </dl>
        </x-card>
        <div class="d-grid gap-2 mb-4">
            @if ($party->isCustomer())
                @can('sales.create')<a href="{{ route('invoices.create', 'sales') }}" class="btn btn-outline-primary">{{ __('New sale') }}</a>@endcan
                @can('vouchers.create')<a href="{{ route('vouchers.create', ['receipt', 'party_id' => $party->id]) }}" class="btn btn-outline-success">{{ __('Receive payment') }}</a>@endcan
            @endif
            @if ($party->isSupplier())
                @can('vouchers.create')<a href="{{ route('vouchers.create', ['payment', 'party_id' => $party->id]) }}" class="btn btn-outline-danger">{{ __('Make payment') }}</a>@endcan
            @endif
            @can('reports.accounts')<a href="{{ route('reports.party-statement', ['party_id' => $party->id]) }}" class="btn btn-outline-secondary">{{ __('Statement of account') }}</a>@endcan
        </div>
    </div>
    <div class="col-lg-8">
        @if ($pendingChallans->isNotEmpty())
            <x-card title="Delivered, not billed" :body="false">
                <x-slot:actions>@can('sales.create')<a href="{{ route('invoices.create', ['sales', 'deliveries' => $pendingChallans->pluck('id')->join(',')]) }}" class="btn btn-sm btn-primary">{{ __('Invoice all') }}</a>@endcan</x-slot:actions>
                <ul class="list-group list-group-flush">
                    @foreach ($pendingChallans as $d)<li class="list-group-item d-flex justify-content-between"><a href="{{ route('deliveries.show', $d) }}">{{ $d->number }}</a><span class="small">{{ $d->date->format('d-m-Y') }}</span></li>@endforeach
                </ul>
            </x-card>
        @endif
        <x-card title="Outstanding invoices" :body="false">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Due') }}</th><th class="num">{{ __('Total') }}</th><th class="num">{{ __('Balance') }}</th></tr></thead>
                <tbody>
                @forelse ($open as $inv)
                    <tr data-href="{{ route('invoices.show', [$inv->type === 'sale' ? 'sales' : 'purchases', $inv]) }}" style="cursor:pointer">
                        <td>{{ $inv->number }}</td><td>{{ $inv->date->format('d-m-Y') }}</td>
                        <td class="{{ $inv->due_date && $inv->due_date->isPast() ? 'text-danger fw-semibold' : '' }}">{{ $inv->due_date?->format('d-m-Y') }}</td>
                        <td class="num">{{ money($inv->total) }}</td><td class="num">{{ money($inv->balance) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted text-center py-3">{{ __('Nothing outstanding.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
        @if (\App\Support\Tenant::business()->feature('customer_prices') && $party->isCustomer())
            <x-card title="Special prices" :body="false">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>{{ __('Product') }}</th><th class="num">{{ __('Standard') }}</th><th class="num">{{ __('This customer') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($specialPrices as $sp)
                        <tr><td>{{ $sp->product->name }}</td><td class="num text-muted">{{ money($sp->product->sale_price) }}</td><td class="num fw-semibold">{{ money($sp->price) }} <small class="text-muted">/{{ $sp->product->unit->short_name }}</small></td>
                            <td class="text-end">@can('parties.edit')<x-delete :action="route('parties.prices.destroy', [$party, $sp])" confirm="Remove this special price?" />@endcan</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">{{ __('No special prices. Price level and default prices apply.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @can('parties.edit')
                    <form method="POST" action="{{ route('parties.prices.store', $party) }}" class="card-body row g-2 align-items-end">
                        @csrf
                        <div class="col-md-7"><label class="form-label small mb-1">{{ __('Product') }}</label><x-product-select class="form-select-sm" /></div>
                        <div class="col-md-3"><label class="form-label small mb-1">{{ __('Price per base unit') }}</label><input type="number" step="any" min="0" name="price" class="form-control form-control-sm" required></div>
                        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">{{ __('Save') }}</button></div>
                    </form>
                @endcan
            </x-card>
        @endif
        <x-card title="Recent documents" :body="false">
            <table class="table table-sm mb-0">
                <tbody>
                @forelse ($invoices as $inv)
                    @php $doc = array_search($inv->type, array_map(fn ($d) => $d[0], \App\Http\Controllers\Trade\InvoiceController::DOCS)); @endphp
                    <tr data-href="{{ route('invoices.show', [$doc, $inv]) }}" style="cursor:pointer">
                        <td>{{ __($inv->title) }}</td><td>{{ $inv->number }}</td><td>{{ $inv->date->format('d-m-Y') }}</td><td class="num">{{ money($inv->total) }}</td><td><x-status :status="$inv->status" /></td>
                    </tr>
                @empty
                    <tr><td class="text-muted text-center py-3">{{ __('No documents yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
</div>
@endsection
