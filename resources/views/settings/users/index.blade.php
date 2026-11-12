@extends('layouts.app')
@section('title', __('Users'))
@section('header-actions')
    @can('users.create')<a href="{{ route('settings.users.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> {{ __('Add user') }}</a>@endcan
@endsection
@section('content')
<x-card :body="false">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>{{ __('Name') }}</th><th>{{ __('Email') }}</th><th>{{ __('Role') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Last login') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $u)
                <tr>
                    <td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>{{ $u->roles->pluck('name')->join(', ') }}</td>
                    <td>{{ $u->branch?->name }}</td><td>{{ $u->last_login_at?->diffForHumans() ?? '—' }}</td>
                    <td>{!! $u->is_active ? '<span class="badge bg-success">'.e(__('Active')).'</span>' : '<span class="badge bg-secondary">'.e(__('Inactive')).'</span>' !!}</td>
                    <td class="text-end text-nowrap">
                        @can('users.edit')<a href="{{ route('settings.users.edit', $u) }}" class="me-2"><i class="fas fa-edit"></i></a>@endcan
                        @can('users.delete')@if ($u->is_active && ! $u->is(auth()->user()))<x-delete :action="route('settings.users.destroy', $u)" confirm="Deactivate this user?" />@endif @endcan
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-card>
@endsection
