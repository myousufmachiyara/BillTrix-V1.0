@extends('layouts.app')
@section('title', 'Roles & Permissions')
@section('header-actions')
    @can('roles.create')<a href="{{ route('settings.roles.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add role</a>@endcan
@endsection
@section('content')
<x-card :body="false">
    <table class="table table-hover mb-0">
        <thead class="table-light"><tr><th>Role</th><th class="num">Permissions</th><th class="num">Users</th><th></th></tr></thead>
        <tbody>
        @foreach ($roles as $role)
            <tr>
                <td class="fw-semibold">{{ $role->name }}</td><td class="num">{{ $role->name === 'Owner' ? 'All' : $role->permissions_count }}</td><td class="num">{{ $role->users_count }}</td>
                <td class="text-end">
                    @if ($role->name !== 'Owner')
                        @can('roles.edit')<a href="{{ route('settings.roles.edit', $role) }}" class="me-2"><i class="fas fa-edit"></i></a>@endcan
                        @can('roles.delete')<x-delete :action="route('settings.roles.destroy', $role)" />@endcan
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-card>
@endsection
