@extends('layouts.guest')
@section('title', 'Sign In')
@section('content')
<form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="form-group mb-3">
        <label class="form-label">Email</label>
        <div class="input-group">
            <input name="email" type="email" class="form-control form-control-lg" value="{{ old('email') }}" required autofocus autocomplete="username">
            <span class="input-group-text"><i class="fas fa-user"></i></span>
        </div>
    </div>
    <div class="form-group mb-3">
        <label class="form-label">Password</label>
        <div class="input-group">
            <input name="password" type="password" class="form-control form-control-lg" required autocomplete="current-password">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
        </div>
    </div>
    <div class="row align-items-center">
        <div class="col-6">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">Remember me</label></div>
        </div>
        <div class="col-6 text-end"><button type="submit" class="btn btn-primary">Sign In</button></div>
    </div>
    @if (config('billtrix.allow_registration'))
        <p class="text-center mt-4 mb-0">New business? <a href="{{ route('register') }}">Create your BillTrix account</a></p>
    @endif
</form>
@endsection
