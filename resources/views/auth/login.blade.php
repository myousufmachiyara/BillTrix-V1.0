@extends('layouts.guest')
@section('title', __('Sign In'))
@section('content')
<form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="form-group mb-3">
        <label class="form-label">{{ __('Username or email') }}</label>
        <div class="input-group">
            <input name="login" type="text" class="form-control form-control-lg" value="{{ old('login') }}" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
            <span class="input-group-text"><i class="fas fa-user"></i></span>
        </div>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">{{ __('Password') }}</label>
        <div class="input-group">
            <input name="password" type="password" class="form-control form-control-lg" required autocomplete="current-password">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
        </div>
    </div>
    <div class="row align-items-center">
        <div class="col-6">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">{{ __('Remember me') }}</label></div>
        </div>
        <div class="col-6 text-end"><button type="submit" class="btn btn-primary">{{ __('Sign In') }}</button></div>
    </div>
    @if (config('billtrix.allow_registration'))
        <p class="text-center mt-4 mb-0">{{ __('New business?') }} <a href="{{ route('register') }}">{{ __('Create your BillTrix account') }}</a></p>
    @endif
</form>
@endsection
