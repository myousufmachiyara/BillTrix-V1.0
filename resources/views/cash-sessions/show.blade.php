@extends('layouts.app')
@section('title', 'Register '.$session->number)
@section('header-actions')
    <a href="{{ route('cash-sessions.index') }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> Registers</a>
@endsection
@section('content')
<div class="row">
    <div class="col-lg-6">
        <x-card title="Shift" :body="false">
            <x-slot:actions>
                <a href="{{ route('cash-sessions.print', $session) }}" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-print"></i> Z-report</a>
                @if ($session->isOpen())<a href="{{ route('cash-sessions.close', $session) }}" class="btn btn-sm btn-warning">Close</a>@endif
            </x-slot:actions>
            <div class="card-body small text-muted">{{ $session->user->name }} · {{ $session->opened_at->format('d M Y H:i') }} – {{ $session->closed_at?->format('d M Y H:i') ?? 'open' }}</div>
            @include('cash-sessions._summary')
        </x-card>
    </div>
    @unless ($session->isOpen())
        <div class="col-lg-6">
            <x-card title="Closing count">
                <table class="table table-sm">
                    <tr><td>Expected</td><td class="num">{{ money($session->expected_cash) }}</td></tr>
                    <tr><td>Counted</td><td class="num">{{ money($session->counted_cash) }}</td></tr>
                    <tr class="fw-bold {{ $session->difference < 0 ? 'text-danger' : ($session->difference > 0 ? 'text-success' : '') }}"><td>{{ $session->difference < 0 ? 'Short' : ($session->difference > 0 ? 'Over' : 'Difference') }}</td><td class="num">{{ money($session->difference) }}</td></tr>
                </table>
                @if ($session->denominations)
                    <table class="table table-sm mb-2">@foreach ($session->denominations as $d => $n)<tr><td>Rs {{ number_format($d) }} × {{ $n }}</td><td class="num">{{ money($d * $n) }}</td></tr>@endforeach</table>
                @endif
                @if ($session->notes)<p class="mb-0"><strong>Notes:</strong> {{ $session->notes }}</p>@endif
                <p class="small text-muted mb-0 mt-2">Closed by {{ $session->closer?->name }}. Any difference was posted to Cash Over / Short.</p>
            </x-card>
        </div>
    @endunless
</div>
@endsection
