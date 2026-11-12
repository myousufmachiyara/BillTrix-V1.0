@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
@if (! $stats)
    <x-card><p class="mb-0">{{ __('Welcome,') }} {{ auth()->user()->name }}{{ __('. Use the menu to get started.') }}</p></x-card>
@else
    <div class="row g-3 mb-3">
        @foreach ([
            ['Sales today', $stats['sales_today'], 'primary', 'fa-cash-register'],
            ['Sales this month', $stats['sales_month'], 'info', 'fa-chart-line'],
            ['Gross profit (month)', $stats['gross_profit_month'], 'success', 'fa-coins'],
            ['Purchases this month', $stats['purchases_month'], 'secondary', 'fa-truck'],
            ['Cash & bank', $stats['cash'], 'success', 'fa-wallet'],
            ['Receivable', $stats['receivable'], 'warning', 'fa-hand-holding-usd'],
            ['Payable', $stats['payable'], 'danger', 'fa-file-invoice-dollar'],
            ['Stock value', $stats['stock_value'], 'dark', 'fa-boxes'],
        ] as [$label, $value, $color, $icon])
            <div class="col-6 col-lg-3">
                <div class="card stat-card h-100" style="border-left-color: var(--bs-{{ $color }})">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-label">{{ __($label) }}</div>
                            <div class="stat-value">{{ money($value, 0) }}</div>
                        </div>
                        <i class="fas {{ $icon }} fa-2x text-{{ $color }} opacity-50"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-8">
            <x-card title="{{ __('Net sales — last 30 days') }}">
                <canvas id="trend" height="110"></canvas>
            </x-card>
            <x-card title="{{ __('Recent documents') }}" :body="false">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <tbody>
                        @forelse ($stats['recent'] as $inv)
                            <tr data-href="{{ route('invoices.show', [$inv->type === 'sale' ? 'sales' : 'purchases', $inv]) }}" style="cursor:pointer">
                                <td>{{ $inv->number }}</td><td>{{ $inv->date->format('d-m-Y') }}</td><td>{{ $inv->party->name }}</td>
                                <td class="num">{{ money($inv->total) }}</td><td><x-status :status="$inv->status" /></td>
                            </tr>
                        @empty
                            <tr><td class="text-muted text-center py-4">{{ __('No invoices yet.') }} <a href="{{ route('invoices.create', 'purchases') }}">{{ __('Record a purchase') }}</a> {{ __('or') }} <a href="{{ route('invoices.create', 'sales') }}">{{ __('make a sale') }}</a>.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="{{ __('Top receivables') }}" :body="false">
                <ul class="list-group list-group-flush">
                    @forelse ($stats['top_debtors'] as $p)
                        <li class="list-group-item d-flex justify-content-between"><a href="{{ route('parties.show', $p) }}">{{ $p->name }}</a><span class="num">{{ money($p->balance) }}</span></li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('Nothing outstanding.') }}</li>
                    @endforelse
                </ul>
            </x-card>
            <x-card title="{{ __('Low stock') }}" :body="false">
                <ul class="list-group list-group-flush">
                    @forelse ($stats['low_stock'] as $p)
                        <li class="list-group-item d-flex justify-content-between"><a href="{{ route('products.show', $p) }}">{{ $p->name }}</a><span class="badge bg-danger">{{ qty($p->qty ?? 0) }} / {{ qty($p->reorder_level) }} {{ $p->unit->short_name }}</span></li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('All products above reorder level.') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
@endif
@endsection

@if ($stats)
@push('scripts')
<script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
<script>
    (function () {
        const data = @js($stats['trend']);
        new Chart(document.getElementById('trend'), {
            type: 'bar',
            data: { labels: Object.keys(data).map(d => d.slice(8) + '/' + d.slice(5, 7)), datasets: [{ data: Object.values(data), backgroundColor: '#0088cc', borderRadius: 3 }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() } }, x: { grid: { display: false } } } },
        });
    })();
</script>
@endpush
@endif
