@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@php
    $groups = [
        'Sales' => ['reports.sales', [
            ['Sales register', route('reports.register', ['type' => 'sale']), 'Every sale invoice with totals, paid and due.'],
            ['Sale returns register', route('reports.register', ['type' => 'sale_return']), 'All returns from customers.'],
        ]],
        'Profit' => ['reports.profit', [
            ['Profit & loss', route('reports.profit-loss'), 'Revenue, cost of sales, expenses and net profit.'],
            ['Product-wise sales & margin', route('reports.product-sales'), 'Quantity, revenue, cost and margin per product.'],
        ]],
        'Purchases' => ['reports.purchases', [
            ['Purchase register', route('reports.register', ['type' => 'purchase']), 'Every purchase invoice with totals.'],
            ['Purchase returns register', route('reports.register', ['type' => 'purchase_return']), 'All returns to suppliers.'],
        ]],
        'Inventory' => ['reports.inventory', [
            ['Stock & valuation', route('reports.stock'), 'Quantity on hand and value at average cost.'],
            ['Stock movement (item ledger)', route('reports.stock-movement'), 'Every in and out for a product.'],
            ['Low stock / reorder', route('reports.low-stock'), 'Items at or below their reorder level.'],
        ]],
        'Accounts' => ['reports.accounts', [
            ['Customer / supplier statement', route('reports.party-statement'), 'Running balance for one party — print or share.'],
            ['Receivables aging', route('reports.aging', ['side' => 'receivable']), 'Who owes you and for how long.'],
            ['Payables aging', route('reports.aging', ['side' => 'payable']), 'What you owe suppliers.'],
            ['Cash & bank book', route('reports.cash-book'), 'Daily cash and bank movements with balance.'],
            ['Account ledger', route('reports.ledger'), 'Any account with opening and running balance.'],
            ['Day book', route('reports.day-book'), 'All entries posted on a day or period.'],
            ['Trial balance', route('reports.trial-balance'), 'Debit and credit balance of every account.'],
            ['Balance sheet', route('reports.balance-sheet'), 'Assets, liabilities and equity.'],
        ]],
    ];
@endphp
<div class="row">
    @foreach ($groups as $group => [$perm, $items])
        @can($perm)
            <div class="col-md-6 col-xl-4">
                <x-card :title="$group" :body="false">
                    <div class="list-group list-group-flush">
                        @foreach ($items as [$label, $url, $desc])
                            <a href="{{ $url }}" class="list-group-item list-group-item-action">
                                <div class="fw-semibold">{{ $label }}</div><div class="small text-muted">{{ $desc }}</div>
                            </a>
                        @endforeach
                    </div>
                </x-card>
            </div>
        @endcan
    @endforeach
</div>
@endsection
