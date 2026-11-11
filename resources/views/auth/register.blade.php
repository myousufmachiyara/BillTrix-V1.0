@extends('layouts.guest')
@section('title', 'Get Started')
@section('content')
<form method="POST" action="{{ route('register') }}">
    @csrf
    <div class="row">
        <x-input name="business_name" label="Business name" col="col-12" required autofocus />
        <div class="col-12 mb-3">
            <label class="form-label">Your business is mainly a…</label>
            <div class="d-flex gap-2">
                @foreach (config('billtrix.categories') as $key => $label)
                    <input type="radio" class="btn-check" name="category" id="cat-{{ $key }}" value="{{ $key }}" @checked(old('category', 'retail') === $key)>
                    <label class="btn btn-outline-primary flex-fill" for="cat-{{ $key }}">{{ $label }}</label>
                @endforeach
            </div>
            <div class="form-text">This only picks sensible starting settings. You can switch any feature on or off later.</div>
        </div>
        <x-input name="name" label="Your name" required />
        <x-input name="phone" label="Phone" />
        <x-input name="email" label="Email" type="email" col="col-12" required />
        <x-input name="password" label="Password" type="password" required minlength="8" />
        <x-input name="password_confirmation" label="Confirm password" type="password" required />
    </div>
    <div class="d-grid"><button class="btn btn-primary btn-lg" type="submit">Create my business</button></div>
    <p class="text-center mt-3 mb-0">Already registered? <a href="{{ route('login') }}">Sign in</a></p>
</form>
@endsection
