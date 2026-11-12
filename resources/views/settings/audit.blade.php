@php
    $types = \App\Http\Controllers\Settings\AuditController::TYPES;
    $events = \App\Http\Controllers\Settings\AuditController::EVENTS;
    $badge = ['created' => 'success', 'updated' => 'primary', 'lines_changed' => 'primary', 'deleted' => 'danger', 'login' => 'secondary', 'logout' => 'secondary', 'login_failed' => 'warning', 'exported' => 'info'];
    $show = function ($v) { return is_array($v) ? (array_is_list($v) ? implode("\n", $v) : json_encode($v, JSON_UNESCAPED_UNICODE)) : (is_bool($v) ? ($v ? 'Yes' : 'No') : (string) $v); };
@endphp
@extends('layouts.app')
@section('title', __('Audit Trail'))
@section('content')
<x-card :body="false">
    <div class="card-body pb-0">
        <form class="row g-2 align-items-end filters">
            <x-date-range />
            <div class="col-md-2"><label class="form-label small mb-1">{{ __('User') }}</label>
                <select name="user_id" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>@foreach ($users as $id => $n)<option value="{{ $id }}" @selected((int) request('user_id') === $id)>{{ $n }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small mb-1">{{ __('Record') }}</label>
                <select name="type" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>@foreach ($types as $k => $v)<option value="{{ $k }}" @selected(request('type') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small mb-1">{{ __('Action') }}</label>
                <select name="event" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>@foreach ($events as $k => $v)<option value="{{ $k }}" @selected(request('event') === $k)>{{ __($v) }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small mb-1">{{ __('Number / name') }}</label><input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm"></div>
            @if (request('id'))<input type="hidden" name="id" value="{{ request('id') }}">@endif
            <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Filter') }}</button> <a href="{{ route('settings.audit') }}" class="btn btn-sm btn-default">{{ __('Reset') }}</a></div>
        </form>
    </div>
    <div class="table-responsive mt-3">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>{{ __('When') }}</th><th>{{ __('User') }}</th><th>{{ __('Action') }}</th><th>{{ __('Record') }}</th><th>{{ __('Details') }}</th><th>{{ __('IP') }}</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-nowrap small">{{ $log->created_at->format('d-m-Y H:i:s') }}</td>
                    <td>{{ $log->user?->name ?? '—' }}</td>
                    <td><span class="badge bg-{{ $badge[$log->event] ?? 'secondary' }}">{{ __($events[$log->event] ?? $log->event) }}</span></td>
                    <td class="small">{{ $log->auditable_type ? __($types[$log->auditable_type] ?? $log->auditable_type) : '' }}<br><strong>{{ $log->label }}</strong></td>
                    <td class="small" style="max-width:520px">
                        @if (in_array($log->event, ['updated', 'lines_changed']))
                            <table class="table table-sm table-borderless mb-0">
                                @foreach ($log->changes() as $field => [$old, $new])
                                    <tr><td class="text-muted text-nowrap">{{ str_replace('_', ' ', $field) }}</td>
                                        <td><del class="text-danger" style="white-space:pre-line">{{ $show($old) }}</del></td><td style="white-space:pre-line">{{ $show($new) }}</td></tr>
                                @endforeach
                            </table>
                        @elseif ($log->event === 'deleted')
                            <span class="text-muted">{{ collect($log->old_values)->only(['number', 'name', 'date', 'total', 'amount', 'type'])->map($show)->map(fn ($v, $k) => "$k: $v")->join(' · ') }}</span>
                        @elseif ($log->new_values)
                            <span class="text-muted">{{ collect($log->new_values)->only(['number', 'name', 'date', 'total', 'amount', 'type', 'report', 'email'])->map($show)->map(fn ($v, $k) => "$k: $v")->join(' · ') }}</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-5">{{ __('Nothing recorded yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())<div class="card-body">{{ $logs->links() }}</div>@endif
</x-card>
@endsection
