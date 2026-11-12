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
            <input name="password" id="password" type="password" class="form-control form-control-lg" required autocomplete="current-password">
            <button type="button" class="input-group-text toggle-password" id="toggle-password" aria-label="{{ __('Show password') }}" title="{{ __('Show password') }}"
                data-show="{{ __('Show password') }}" data-hide="{{ __('Hide password') }}" style="cursor:pointer">
                <i class="fas fa-eye"></i>
            </button>
        </div>
    </div>
    <div class="row align-items-center">
        <div class="col-6">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remember" id="remember"><label class="form-check-label" for="remember">{{ __('Remember me') }}</label></div>
        </div>
        <div class="col-6 text-end"><button type="submit" class="btn btn-primary">{{ __('Sign In') }}</button></div>
    </div>
</form>
<script>
document.getElementById('toggle-password').addEventListener('click', function () {
    var input = document.getElementById('password'), show = input.type === 'password', icon = this.querySelector('i');
    input.type = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    this.setAttribute('aria-label', show ? this.dataset.hide : this.dataset.show);
    this.title = this.getAttribute('aria-label');
    input.focus();
});
</script>
@endsection
