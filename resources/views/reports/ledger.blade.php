@extends('layouts.app')
@section('title', isset($cashBook) ? __('Cash & Bank Book') : __('Account Ledger'))
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <div class="col-md-4">
        <label class="form-label small mb-1">{{ __('Account') }}</label>
        <select name="account_id" class="form-select form-select-sm select2">
            <option value="">{{ __('Choose…') }}</option>
            @foreach ($accounts as $a)<option value="{{ $a->id }}" @selected($account?->id === $a->id)>{{ $a->code }} — {{ $a->name }}</option>@endforeach
        </select>
    </div>
    <x-date-range :from="$from" :to="$to" />
    <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Show') }}</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button> <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-sm btn-default" title="{{ __('Excel') }}"><i class="fas fa-file-excel text-success"></i></a></div>
</form>
@if ($data)
    @include('reports._header', ['title' => $account->code.' — '.$account->name, 'period' => \Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')])
    @include('reports._ledger-table')
@else
    <x-card><p class="text-muted mb-0">{{ __('Choose an account to see its ledger.') }}</p></x-card>
@endif
@endsection
