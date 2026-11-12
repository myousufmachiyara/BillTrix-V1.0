@extends('layouts.app')
@section('title', __('Registers & Cash Closing'))
@section('content')
<div class="row">
    <div class="col-lg-4">
        @can('pos.use')
            @if ($mine)
                <x-card title="Your register is open">
                    <p class="mb-1"><strong>{{ $mine->number }}</strong> · {{ $mine->account->name }}</p>
                    <p class="text-muted small">{{ __('Opened') }} {{ $mine->opened_at->format('d M H:i') }} {{ __('with') }} {{ money($mine->opening_float) }}</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('pos') }}" class="btn btn-primary"><i class="fas fa-cash-register"></i> {{ __('Go to POS') }}</a>
                        <a href="{{ route('cash-sessions.close', $mine) }}" class="btn btn-warning"><i class="fas fa-lock"></i> {{ __('Count cash & close') }}</a>
                    </div>
                </x-card>
            @else
                <x-card title="Open register">
                    <form method="POST" action="{{ route('cash-sessions.store') }}" class="row">
                        @csrf
                        <x-select name="account_id" label="Cash drawer" :options="$cashAccounts->pluck('name', 'id')" required col="col-12" />
                        <x-input name="opening_float" label="Opening cash (float)" type="number" step="0.01" min="0" value="0" required col="col-12" help="Cash already in the drawer for change." class="form-control form-control-lg text-end" />
                        <div class="col-12"><button class="btn btn-success btn-lg w-100"><i class="fas fa-lock-open"></i> {{ __('Open & start selling') }}</button></div>
                    </form>
                </x-card>
            @endif
        @endcan
    </div>
    <div class="col-lg-8">
        <x-card :body="false" title="Register history">
            <x-slot:actions>
                <form class="d-flex gap-2"><input type="date" name="date" value="{{ request('date') }}" class="form-control form-control-sm"><button class="btn btn-sm btn-default">{{ __('Go') }}</button></form>
            </x-slot:actions>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light"><tr><th>{{ __('Register') }}</th><th>{{ __('Cashier') }}</th><th>{{ __('Opened') }}</th><th>{{ __('Closed') }}</th><th class="num">{{ __('Expected') }}</th><th class="num">{{ __('Counted') }}</th><th class="num">{{ __('Over / short') }}</th></tr></thead>
                    <tbody>
                    @forelse ($sessions as $s)
                        <tr data-href="{{ route('cash-sessions.show', $s) }}" style="cursor:pointer">
                            <td class="fw-semibold">{{ $s->number }}</td><td>{{ $s->user->name }}</td><td>{{ $s->opened_at->format('d-m H:i') }}</td>
                            <td>{!! $s->closed_at ? e($s->closed_at->format('d-m H:i')) : '<span class="badge bg-success">'.e(__('Open')).'</span>' !!}</td>
                            <td class="num">{{ $s->expected_cash !== null ? money($s->expected_cash) : '' }}</td><td class="num">{{ $s->counted_cash !== null ? money($s->counted_cash) : '' }}</td>
                            <td class="num {{ $s->difference < 0 ? 'text-danger' : ($s->difference > 0 ? 'text-success' : '') }}">{{ $s->difference !== null ? money($s->difference) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No registers yet.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($sessions->hasPages())<div class="card-body">{{ $sessions->links() }}</div>@endif
        </x-card>
    </div>
</div>
@endsection
