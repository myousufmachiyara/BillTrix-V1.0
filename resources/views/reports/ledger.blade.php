@extends('layouts.app')
@section('title', isset($cashBook) ? 'Cash & Bank Book' : 'Account Ledger')
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <div class="col-md-4">
        <label class="form-label small mb-1">Account</label>
        <select name="account_id" class="form-select form-select-sm select2">
            <option value="">Choose…</option>
            @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($account?->id === $a->id)>{{ $a->code }} — {{ $a->name }}</option>@endforeach
        </select>
    </div>
    <x-date-range :from="$from" :to="$to" />
    <div class="col-auto"><button class="btn btn-sm btn-primary">Show</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button></div>
</form>
@if ($data)
    @include('reports._header', ['title' => $account->code.' — '.$account->name, 'period' => \Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')])
    @include('reports._ledger-table')
@else
    <x-card><p class="text-muted mb-0">Choose an account to see its ledger.</p></x-card>
@endif
@endsection
