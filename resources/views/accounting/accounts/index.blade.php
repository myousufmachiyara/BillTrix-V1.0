@extends('layouts.app')
@section('title', __('Chart of Accounts'))
@section('header-actions')
    @can('accounts.create')<a href="{{ route('accounts.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('Add account') }}</a>@endcan
@endsection
@section('content')
@php
    $render = function ($parentId, $level) use (&$render, $tree, $balances) {
        foreach ($tree->get($parentId, collect()) as $a) {
            $bal = (float) ($balances[$a->id] ?? 0);
            if ($a->is_group) {
                $ids = collect([$a->id]);
                $stack = [$a->id];
                while ($stack) { $id = array_pop($stack); foreach ($tree->get($id, collect()) as $c) { $ids->push($c->id); $stack[] = $c->id; } }
                $bal = $ids->sum(fn ($id) => (float) ($balances[$id] ?? 0));
            }
            $shown = in_array($a->type, ['asset', 'expense']) ? $bal : -$bal;
            echo view('accounting.accounts._row', ['a' => $a, 'level' => $level, 'balance' => $shown])->render();
            if ($a->is_group) $render($a->id, $level + 1);
        }
    };
@endphp
<x-card :body="false">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 coa-tree">
            <thead class="table-light"><tr><th>{{ __('Account') }}</th><th>{{ __('Type') }}</th><th class="num">{{ __('Balance') }}</th><th></th></tr></thead>
            <tbody>@php $render(0, 0) @endphp</tbody>
        </table>
    </div>
</x-card>
<p class="text-muted small">Customer and supplier balances sit inside Accounts Receivable and Accounts Payable; see each party for its own ledger. Accounts with a lock are used by automatic postings.</p>
@endsection
