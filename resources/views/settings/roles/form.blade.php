@extends('layouts.app')
@section('title', $role->exists ? 'Edit Role: '.$role->name : 'Add Role')
@section('content')
<form method="POST" action="{{ $role->exists ? route('settings.roles.update', $role) : route('settings.roles.store') }}">
    @csrf @if ($role->exists) @method('PUT') @endif
    <x-card>
        <div class="row"><x-input name="name" label="Role name" :value="$role->name" required col="col-md-4" /></div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light"><tr><th>Module</th><th>Permissions <a href="#" class="small ms-2" onclick="document.querySelectorAll('.perm').forEach(c=>c.checked=true);return false">select all</a></th></tr></thead>
                <tbody>
                @foreach (config('billtrix.permissions') as $module => $actions)
                    <tr>
                        <td class="fw-semibold text-nowrap">{{ ucwords(str_replace('_', ' ', $module)) }}</td>
                        <td>
                            @foreach ($actions as $action)
                                @php $perm = "$module.$action"; @endphp
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input perm" type="checkbox" name="permissions[]" value="{{ $perm }}" id="p-{{ $perm }}" @checked(in_array($perm, old('permissions', $granted)))>
                                    <label class="form-check-label" for="p-{{ $perm }}">{{ ucwords(str_replace('_', ' ', $action)) }}</label>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="text-end"><a href="{{ route('settings.roles.index') }}" class="btn btn-default">Cancel</a> <button class="btn btn-primary">Save role</button></div>
    </x-card>
</form>
@endsection
