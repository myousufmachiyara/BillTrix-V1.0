@extends('layouts.app')
@section('title', __('Demo Leads'))
@section('header-actions')
    <a href="{{ route('demo-leads.export', request()->query()) }}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> {{ __('Export to Excel') }}</a>
@endsection
@section('content')
@php($statuses = config('billtrix.demo.statuses'))
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('demo-leads.index') }}" class="btn btn-sm {{ request('status') ? 'btn-light' : 'btn-primary' }}">{{ __('All') }} <span class="badge bg-white text-dark ms-1">{{ $counts->sum() }}</span></a>
    @foreach ($statuses as $key => $label)
        <a href="{{ route('demo-leads.index', ['status' => $key]) }}" class="btn btn-sm {{ request('status') === $key ? 'btn-primary' : 'btn-light' }}">{{ __($label) }} <span class="badge bg-white text-dark ms-1">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
    <span class="ms-auto align-self-center text-muted">{{ __('New today') }}: <strong>{{ $today }}</strong></span>
</div>

<x-card>
    <form method="GET" class="row g-2 align-items-end mb-0">
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <div class="col-md-4"><label class="form-label">{{ __('Search') }}</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Name, phone, business or city') }}"></div>
        <div class="col-md-2"><label class="form-label">{{ __('From') }}</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">{{ __('To') }}</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-search"></i> {{ __('Filter') }}</button></div>
    </form>
</x-card>

<x-card :body="false">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>{{ __('First visit') }}</th><th>{{ __('Lead') }}</th><th>{{ __('Phone') }}</th><th>{{ __('City / type') }}</th>
                <th>{{ __('Source') }}</th><th class="text-center">{{ __('Visits') }}</th><th style="min-width:330px">{{ __('Follow-up') }}</th>
            </tr></thead>
            <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td class="text-nowrap">{{ $lead->created_at?->format('d M Y') }}<div class="text-muted small">{{ __('Last') }}: {{ $lead->last_seen_at?->diffForHumans() }}</div></td>
                    <td><strong>{{ $lead->name }}</strong>@if ($lead->business_name)<div class="small">{{ $lead->business_name }}</div>@endif</td>
                    <td class="text-nowrap">
                        <div>{{ $lead->phone }}</div>
                        <a href="{{ $lead->whatsappUrl() }}" target="_blank" rel="noopener" class="btn btn-success btn-xs py-0 px-2 mt-1"><i class="fab fa-whatsapp"></i> {{ __('WhatsApp') }}</a>
                        <a href="tel:{{ $lead->phone }}" class="btn btn-light btn-xs py-0 px-2 mt-1"><i class="fas fa-phone"></i> {{ __('Call') }}</a>
                    </td>
                    <td>{{ $lead->city ?: '—' }}<div class="text-muted small">{{ $lead->business_type ? __($lead->business_type) : '' }}</div></td>
                    <td>{{ $lead->source ?: '—' }}@if ($lead->campaign)<div class="text-muted small">{{ $lead->campaign }}</div>@endif</td>
                    <td class="text-center">{{ $lead->visits }}</td>
                    <td>
                        <form method="POST" action="{{ route('demo-leads.update', $lead) }}" class="d-flex gap-1">
                            @csrf @method('PUT')
                            <select name="status" class="form-select form-select-sm" style="max-width:130px" aria-label="{{ __('Status') }}">
                                @foreach ($statuses as $key => $label)<option value="{{ $key }}" @selected($lead->status === $key)>{{ __($label) }}</option>@endforeach
                            </select>
                            <input name="notes" value="{{ $lead->notes }}" class="form-control form-control-sm" placeholder="{{ __('Notes') }}" maxlength="2000" aria-label="{{ __('Notes') }}">
                            <button class="btn btn-primary btn-sm" title="{{ __('Save') }}" aria-label="{{ __('Save') }}"><i class="fas fa-check"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No leads yet. Share your demo link: :url', ['url' => url('/demo?utm_source=whatsapp')]) }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
{{ $leads->links() }}
@endsection
