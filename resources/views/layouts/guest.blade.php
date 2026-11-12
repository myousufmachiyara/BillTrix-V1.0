<!DOCTYPE html>
<html lang="en" class="fixed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') {{ __('· BillTrix') }}</title>
    <link rel="shortcut icon" href="{{ asset('assets/img/favicon.png') }}">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/font-awesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/skins/default.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/billtrix.css') }}?v={{ @filemtime(public_path('assets/css/billtrix.css')) }}">
</head>
<body>
<section class="body-sign">
    <div class="center-sign">
        <a href="{{ route('login') }}" class="logo float-start"><img src="{{ asset('assets/img/billtrix-logo-black.png') }}" height="54" alt="BillTrix"></a>
        <div class="panel card-sign">
            <div class="card-title-sign mt-3 text-end">
                <h2 class="title text-uppercase font-weight-bold m-0"><i class="fas fa-user me-1"></i> @yield('title')</h2>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif
                @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @yield('content')
            </div>
        </div>
        <p class="text-center text-muted mt-3 mb-3">&copy; {{ date('Y') }} {{ __('BillTrix — software that adapts to your business.') }}</p>
    </div>
</section>
</body>
</html>
