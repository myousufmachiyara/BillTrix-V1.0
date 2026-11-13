@extends('layouts.guest')
@section('title', config('billtrix.demo.enabled') ? __('Free Demo') : __('Sign In'))
@section('content')
@if (config('billtrix.demo.enabled'))
    <p class="mb-3">{{ __('See BillTrix working with real sample data. Enter your details and you will be signed in straight away — no password needed.') }}</p>
    <form method="POST" action="{{ route('demo.start') }}" novalidate>
        @csrf
        <div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="form-group mb-3">
            <label class="form-label" for="demo-name">{{ __('Your name') }} <span class="text-danger">*</span></label>
            <input id="demo-name" name="name" type="text" class="form-control form-control-lg" value="{{ old('name') }}" required maxlength="80" autocomplete="name" autofocus>
        </div>
        <div class="form-group mb-3">
            <label class="form-label" for="demo-phone">{{ __('Mobile / WhatsApp number') }} <span class="text-danger">*</span></label>
            <input id="demo-phone" name="phone" type="tel" inputmode="tel" class="form-control form-control-lg" value="{{ old('phone') }}" required maxlength="25" placeholder="03XX XXXXXXX" autocomplete="tel">
        </div>
        <div class="form-group mb-3">
            <label class="form-label" for="demo-business">{{ __('Business name') }}</label>
            <input id="demo-business" name="business_name" type="text" class="form-control" value="{{ old('business_name') }}" maxlength="120" autocomplete="organization">
        </div>
        <div class="row g-2">
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="demo-city">{{ __('City') }}</label>
                <input id="demo-city" name="city" type="text" class="form-control" value="{{ old('city') }}" maxlength="60" autocomplete="address-level2">
            </div>
            <div class="col-sm-6 mb-3">
                <label class="form-label" for="demo-type">{{ __('Business type') }}</label>
                <select id="demo-type" name="business_type" class="form-select">
                    <option value="">{{ __('Choose…') }}</option>
                    @foreach (config('billtrix.demo.business_types') as $t)
                        <option value="{{ $t }}" @selected(old('business_type') === $t)>{{ __($t) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg w-100"><i class="fas fa-play me-1"></i> {{ __('Open the free demo') }}</button>
        <p class="text-muted small text-center mt-2 mb-0">{{ __('We only use your number to help you get started with BillTrix.') }}</p>
    </form>
    <details class="mt-4 pt-3 border-top" @if ($errors->has('login') || old('login')) open @endif>
        <summary class="text-muted" style="cursor:pointer">{{ __('Staff sign in') }}</summary>
        <div class="mt-3">
            @include('auth.partials.signin')
        </div>
    </details>
@else
    @include('auth.partials.signin')
@endif
@endsection
