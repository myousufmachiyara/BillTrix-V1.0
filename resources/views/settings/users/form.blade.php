@extends('layouts.app')
@section('title', $user->exists ? 'Edit User' : 'Add User')
@section('content')
<div class="row"><div class="col-lg-8">
<x-card>
    <form method="POST" action="{{ $user->exists ? route('settings.users.update', $user) : route('settings.users.store') }}" class="row" autocomplete="off">
        @csrf @if ($user->exists) @method('PUT') @endif
        <x-input name="name" label="Name" :value="$user->name" required />
        <x-input name="email" label="Email (login)" type="email" :value="$user->email" required />
        <x-input name="phone" label="Phone" :value="$user->phone" />
        <x-select name="role" label="Role" :options="$roles->combine($roles)" :value="$user->roles->first()?->name" required />
        <x-select name="branch_id" label="Branch" :options="$branches" :value="$user->branch_id" required />
        <x-check name="is_active" label="Active (can sign in)" :checked="$user->is_active" />
        <x-input name="password" label="{{ $user->exists ? 'New password (leave blank to keep)' : 'Password' }}" type="password" :required="! $user->exists" autocomplete="new-password" />
        <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
        <div class="col-12 text-end"><a href="{{ route('settings.users.index') }}" class="btn btn-default">Cancel</a> <button class="btn btn-primary">Save</button></div>
    </form>
</x-card>
</div></div>
@endsection
