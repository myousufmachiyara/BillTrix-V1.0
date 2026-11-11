<!DOCTYPE html>
<html lang="en" class="fixed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $business?->name ?? 'BillTrix' }}</title>
    <link rel="shortcut icon" href="{{ asset('assets/img/favicon.png') }}">
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
    <link rel="stylesheet" href="{{ asset('assets/css/billtrix.css') }}">
    @stack('styles')
</head>
<body>
<section class="body">
    <header class="header">
        <div class="logo-container">
            <a href="{{ route('dashboard') }}" class="logo">
                <img src="{{ asset('assets/img/billtrix-logo-black.png') }}" height="35" alt="BillTrix">
            </a>
            <div class="d-md-none toggle-sidebar-left" data-toggle-class="sidebar-left-opened" data-target="html" data-fire-event="sidebar-left-opened">
                <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
            </div>
        </div>

        <div class="header-right">
            <span class="d-none d-lg-inline text-muted me-3 small">
                <i class="fas fa-store me-1"></i>{{ $business?->name }} · {{ auth()->user()->branch?->name }}
            </span>
            @if ($business?->feature('pos') && auth()->user()->can('pos.use'))
                <a href="{{ route('pos') }}" class="btn btn-success btn-sm me-2 d-none d-sm-inline-block" title="Point of sale"><i class="fas fa-cash-register"></i> POS</a>
            @endif
            @can('sales.create')
                <a href="{{ route('invoices.create', 'sales') }}" class="btn btn-primary btn-sm me-3 d-none d-sm-inline-block" title="New sale (Alt+N)" accesskey="n">
                    <i class="fas fa-plus"></i> New Sale
                </a>
            @endcan
            <span class="separator"></span>
            <div id="userbox" class="userbox">
                <a href="#" data-bs-toggle="dropdown">
                    <div class="profile-info">
                        <span class="name">{{ auth()->user()->name }}</span>
                        <span class="role">{{ auth()->user()->roles->first()?->name }}</span>
                    </div>
                    <i class="fa custom-caret"></i>
                </a>
                <div class="dropdown-menu">
                    <ul class="list-unstyled mb-2">
                        <li class="divider"></li>
                        <li><a role="menuitem" href="#changePassword" class="modal-with-zoom-anim"><i class="fas fa-lock"></i> Change Password</a></li>
                        @can('settings.manage')
                            <li><a role="menuitem" href="{{ route('settings.business') }}"><i class="fas fa-cog"></i> Settings</a></li>
                        @endcan
                        <li>
                            <form action="{{ route('logout') }}" method="POST">@csrf
                                <button type="submit" class="btn btn-link text-start w-100 px-3 text-decoration-none text-reset"><i class="fas fa-power-off"></i> Logout</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <div class="inner-wrapper">
        @include('layouts.sidebar')

        <section role="main" class="content-body">
            <div class="bt-pagebar">
                <h2>@yield('title', 'Dashboard')</h2>
                <div class="d-flex flex-wrap gap-2 no-print">@yield('header-actions')</div>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <strong>Please fix the following:</strong>
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
        <header class="card-header"><h2 class="card-title">Change Password</h2></header>
        <div class="card-body">
            <div class="mb-3"><label class="form-label">Current password</label><input type="password" name="current_password" class="form-control" required autocomplete="current-password"></div>
            <div class="mb-3"><label class="form-label">New password</label><input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password"></div>
            <div><label class="form-label">Confirm new password</label><input type="password" name="password_confirmation" class="form-control" minlength="8" required autocomplete="new-password"></div>
        </div>
        <footer class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Change Password</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
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
<script src="{{ asset('assets/js/billtrix.js') }}"></script>
@if (session('print'))
    <script>window.open(@json(session('print')), '_blank');</script>
@endif
@stack('scripts')
</body>
</html>
