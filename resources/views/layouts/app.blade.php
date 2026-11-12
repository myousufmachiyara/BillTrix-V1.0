<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="fixed js csstransforms csstransforms3d">
<head>
    <meta charset="UTF-8">
    <script>try { if (localStorage.getItem('bt-sidebar-collapsed') === '1' && innerWidth >= 768) document.documentElement.classList.add('sidebar-left-collapsed'); } catch (e) {}</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __(html_entity_decode(trim($__env->yieldContent('title', 'Dashboard')), ENT_QUOTES)) }} · {{ $business?->name ?? 'BillTrix' }}</title>
    <link rel="shortcut icon" href="{{ asset('assets/img/favicon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="{{ asset('assets/icons/apple-touch-icon.png') }}">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/animate/animate.compat.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/font-awesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/magnific-popup/magnific-popup.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2/css/select2.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/select2-bootstrap-theme/select2-bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/skins/default.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/billtrix.css') }}?v={{ @filemtime(public_path('assets/css/billtrix.css')) }}">
    @if (app()->getLocale() === 'ur')
        <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;600&display=swap" rel="stylesheet">
    @endif
    <style>
        /* From the BillTrix base theme layout */
        .cust-pad { padding-top: 0; }
        html:not(.sidebar-left-collapsed) #sidebar-left .nano,
        html:not(.sidebar-left-collapsed) #sidebar-left .nano-content { overflow-x: hidden; }
        #sidebar-left ul.nav-main { margin-right: 0; }
        @media (min-width: 768px) { html:not(.sidebar-left-collapsed) #sidebar-left { overflow: hidden; } }
        @media (min-width: 768px) {
            .cust-pad { padding: 60px 10px 0px 20px; }
            .sidebar-logo { width: 60%; height: auto; padding-top: 5px; }
        }
        @media (max-width: 767px) {
            .sidebar-logo { width: 140px; height: auto; padding-top: 12px; }
            .sidebar-left .sidebar-header .toggle-sidebar-left { padding-top: 14px; }
        }
    </style>
    @stack('styles')
</head>
<body class="lang-{{ app()->getLocale() }}">
<section class="body">
    {{-- Top bar: same markup as the BillTrix base theme (FFK / HCF / SAB); the logo lives in the sidebar --}}
    <header class="page-header">
        <div class="logo-container d-none d-md-block">
            <div id="userbox" class="userbox" style="float:right !important;">
                <a href="#" data-bs-toggle="dropdown" style="margin-right: 20px;">
                    <div class="profile-info">
                        <span class="name">{{ auth()->user()->name }}</span>
                        <span class="role">{{ auth()->user()->roles->first()?->name }}</span>
                    </div>
                    <i class="fa custom-caret"></i>
                </a>
                <div class="dropdown-menu">
                    <ul class="list-unstyled mb-2">
                        <li class="divider"></li>
                        <li><a role="menuitem" href="#changePassword" class="modal-with-zoom-anim"><i class="fas fa-lock"></i> {{ __('Change Password') }}</a></li>
                        <li>
                            <form action="{{ route('locale.change') }}" method="POST">@csrf @method('PUT')
                                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ur' ? 'en' : 'ur' }}">
                                <button type="submit" style="background:transparent;border:none;font-size:14px;"><i class="fas fa-language"></i> {{ app()->getLocale() === 'ur' ? __('English') : 'اردو' }}</button>
                            </form>
                        </li>
                        <li class="d-none" id="install-app-item"><a role="menuitem" href="#" id="install-app"><i class="fas fa-mobile-alt"></i> {{ __('Install app') }}</a></li>
                        @can('settings.manage')
                            <li><a role="menuitem" href="{{ route('settings.business') }}"><i class="fas fa-cog"></i> {{ __('Settings') }}</a></li>
                        @endcan
                        <li>
                            <form action="{{ route('logout') }}" method="POST" id="logout-form">@csrf
                                <button type="submit" style="background:transparent;border:none;font-size:14px;"><i class="fas fa-power-off"></i> {{ __('Logout') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="bt-quick" style="float:right; display:flex; align-items:center; gap:8px; height:50px; margin-right:16px;">
                <span class="d-none d-lg-inline text-muted small me-2"><i class="fas fa-store me-1"></i>{{ $business?->name }} · {{ auth()->user()->branch?->name }}</span>
                @if ($business?->feature('pos') && auth()->user()->can('pos.use'))
                    <a href="{{ route('pos') }}" class="btn btn-success btn-sm" title="{{ __('Point of sale') }}"><i class="fas fa-cash-register"></i> {{ __('POS') }}</a>
                @endif
                @can('sales.create')
                    <a href="{{ route('invoices.create', 'sales') }}" class="btn btn-primary btn-sm" title="{{ __('New sale (Alt+N)') }}" accesskey="n"><i class="fas fa-plus"></i> {{ __('New Sale') }}</a>
                @endcan
            </div>
        </div>

        <div class="logo-container d-md-none">
            <a href="{{ route('dashboard') }}" class="logo">
                <img class="pt-2" src="{{ asset('assets/img/billtrix-logo-black.png') }}" width="35%" alt="BillTrix">
            </a>
            <div class="userbox" style="float:right !important;">
                <a href="#" data-bs-toggle="dropdown" style="margin-right: 20px;">
                    <div class="profile-info">
                        <span class="name">{{ auth()->user()->name }}</span>
                        <span class="role">{{ auth()->user()->roles->first()?->name }}</span>
                    </div>
                    <i class="fa custom-caret"></i>
                </a>
                <div class="dropdown-menu">
                    <ul class="list-unstyled mb-2">
                        <li class="divider"></li>
                        <li><a role="menuitem" href="#changePassword" class="modal-with-zoom-anim"><i class="fas fa-lock"></i> {{ __('Change Password') }}</a></li>
                        <li>
                            <form action="{{ route('locale.change') }}" method="POST">@csrf @method('PUT')
                                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'ur' ? 'en' : 'ur' }}">
                                <button type="submit" style="background:transparent;border:none;font-size:14px;"><i class="fas fa-language"></i> {{ app()->getLocale() === 'ur' ? __('English') : 'اردو' }}</button>
                            </form>
                        </li>
                        <li class="d-none"><a role="menuitem" href="#"><i class="fas fa-mobile-alt"></i> {{ __('Install app') }}</a></li>
                        @can('settings.manage')
                            <li><a role="menuitem" href="{{ route('settings.business') }}"><i class="fas fa-cog"></i> {{ __('Settings') }}</a></li>
                        @endcan
                        <li>
                            <form action="{{ route('logout') }}" method="POST" class="logout-form">@csrf
                                <button type="submit" style="background:transparent;border:none;font-size:14px;"><i class="fas fa-power-off"></i> {{ __('Logout') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
                <i class="fas fa-bars toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened" aria-label="{{ __('Toggle sidebar') }}"></i>
            </div>
        </div>
    </header>

    <div class="inner-wrapper cust-pad">
        @include('layouts.sidebar')

        <section role="main" class="content-body">
            <div class="bt-pagebar">
                <h2>{{ __(html_entity_decode(trim($__env->yieldContent('title', 'Dashboard')), ENT_QUOTES)) }}</h2>
                <div class="d-flex flex-wrap gap-2 no-print">@yield('header-actions')</div>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-1"></i> {{ __(session('success')) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-1"></i> {{ __(session('error')) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>{{ __('Please fix the following:') }}</strong>
                    <ul class="mb-0 mt-1">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </section>
    </div>
</section>

{{-- Change password --}}
<div id="changePassword" class="zoom-anim-dialog modal-block modal-block-primary mfp-hide">
    <form method="POST" action="{{ route('password.change') }}" class="card">
        @csrf @method('PUT')
        <header class="card-header"><h2 class="card-title">{{ __('Change Password') }}</h2></header>
        <div class="card-body">
            <div class="mb-3"><label class="form-label">{{ __('Current password') }}</label><input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
            <div class="mb-3"><label class="form-label">{{ __('New password') }}</label><input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password"></div>
            <div><label class="form-label">{{ __('Confirm new password') }}</label><input type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password"></div>
        </div>
        <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">{{ __('Change Password') }}</button>
            <button type="button" class="btn btn-default modal-dismiss">{{ __('Cancel') }}</button>
        </footer>
    </form>
</div>

<script>
    window.Modernizr = window.Modernizr || { overflowscrolling: false };
    window.BT = { urls: { parties: @js(route('parties.lookup')), products: @js(route('products.lookup')) } };
</script>
<script src="{{ asset('assets/vendor/jquery/jquery.min.js') }}"></script>
<script>jQuery.browser = jQuery.browser || { mobile: /Mobi|Android/i.test(navigator.userAgent) };</script>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/vendor/nanoscroller/nanoscroller.js') }}"></script>
<script src="{{ asset('assets/vendor/magnific-popup/jquery.magnific-popup.min.js') }}"></script>
<script src="{{ asset('assets/vendor/select2/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/js/theme.js') }}"></script>
<script src="{{ asset('assets/js/theme.init.js') }}"></script>
<script>window.BT_SW = @js(asset('sw.js'));
    window.BT_I18N = @js(app()->getLocale() === 'en' ? null : collect(['Search…', 'Search product…', 'In stock (base units)', 'in stock', 'pending', 'of', 'Batch', 'Auto — earliest expiry first', 'exp',
        'Batch no.', 'Expiry', 'Remove', 'Below cost', 'This bill alone exceeds the credit limit of', 'Add at least one item.', 'Balance', 'Account…', 'Difference', 'Balanced',
        'Choose challans of one customer'])->mapWithKeys(fn ($k) => [$k => __($k)]));</script>
<script src="{{ asset('assets/js/billtrix.js') }}?v={{ @filemtime(public_path('assets/js/billtrix.js')) }}"></script>
@if (session('print'))
    <script>window.open(@json(session('print')), '_blank');</script>
@endif
@stack('scripts')
</body>
</html>
