@extends('layouts.app')
@section('title', $user->exists ? __('Edit User') : __('Add User'))
@section('content')
<div class="row"><div class="col-lg-8">
<x-card>
    <form method="POST" action="{{ $user->exists ? route('settings.users.update', $user) : route('settings.users.store') }}" class="row" autocomplete="off">
        @csrf @if ($user->exists) @method('PUT') @endif
        <x-input name="name" label="Name" :value="$user->name" required />
        <x-input name="email" label="Email (login)" type="email" :value="$user->email" required />
        <x-input name="username" label="Username (login)" :value="$user->username" help="Optional. Letters, numbers, - and _ only." />
        <x-input name="phone" label="Phone" :value="$user->phone" />
        <x-select name="locale" label="Language" :options="['en' => 'English', 'ur' => 'اردو (Urdu)']" :value="$user->locale ?? 'en'" />
        <x-select name="role" label="Role" :options="$roles->combine($roles)" :value="$user->roles->first()?->name" required />
        <x-select name="branch_id" label="Branch" :options="$branches" :value="$user->branch_id" required />
        <x-check name="is_active" label="Active (can sign in)" :checked="$user->is_active" />
        <x-input name="password" label="{{ $user->exists ? __('New password (leave blank to keep)') : __('Password') }}" type="password" :required="! $user->exists" autocomplete="new-password" />
        <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
        <div class="col-12 text-end"><a href="{{ route('settings.users.index') }}" class="btn btn-default">{{ __('Cancel') }}</a> <button class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</x-card>
@if ($user->exists)
    <x-card title="Mobile app devices" :body="false">
        <table class="table table-sm mb-0">
            <tbody>
            @forelse ($tokens as $t)
                <tr><td><i class="fas fa-mobile-alt me-1"></i> {{ $t->name }}</td><td class="small text-muted">{{ __('Signed in') }} {{ $t->created_at->format('d M Y') }} {{ __('· last used') }} {{ $t->last_used_at?->diffForHumans() ?? 'never' }}</td>
                    <td class="text-end"><x-delete :action="route('settings.users.tokens.destroy', [$user, $t->id])" confirm="Sign this device out?" label="Sign out" /></td></tr>
            @empty
                <tr><td class="text-muted">{{ __('Not signed in on any device.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </x-card>
@endif
</div></div>
@endsection
