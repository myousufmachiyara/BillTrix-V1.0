<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BillTrix — Login</title>
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/font-awesome/css/all.min.css') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #0d6efd 60%, #0dcaf0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
            padding: 1.5rem;
        }

        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #1e3a5f, #0d6efd);
            padding: 2.5rem 2rem 2rem;
            text-align: center;
            color: #fff;
        }

        .login-header img {
            height: 48px;
            margin-bottom: .75rem;
            /* Fallback text logo if image not found */
        }

        .login-header h1 {
            font-size: 1.6rem;
            font-weight: 700;
            margin: 0 0 .25rem;
            letter-spacing: -.5px;
        }

        .login-header p {
            font-size: .82rem;
            opacity: .8;
            margin: 0;
        }

        .login-body {
            padding: 2rem;
        }

        .form-label {
            font-size: .83rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: .35rem;
        }

        .form-control {
            border-radius: 8px;
            border: 1.5px solid #dee2e6;
            padding: .65rem 1rem;
            font-size: .9rem;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,.15);
            outline: none;
        }

        .input-group-text {
            border-radius: 8px 0 0 8px;
            background: #f8f9fa;
            border: 1.5px solid #dee2e6;
            border-right: 0;
            color: #6c757d;
        }

        .input-group .form-control {
            border-radius: 0 8px 8px 0;
        }

        .btn-login {
            background: linear-gradient(135deg, #1e3a5f, #0d6efd);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            font-size: .95rem;
            padding: .75rem;
            width: 100%;
            transition: opacity .2s, transform .1s;
            cursor: pointer;
        }

        .btn-login:hover   { opacity: .92; transform: translateY(-1px); }
        .btn-login:active  { transform: translateY(0); }

        .alert-danger {
            border-radius: 8px;
            font-size: .85rem;
        }

        .login-footer {
            text-align: center;
            padding: 1rem 2rem 1.5rem;
            font-size: .78rem;
            color: #adb5bd;
        }

        /* Demo credentials badge */
        .demo-badge {
            background: #f0f7ff;
            border: 1px solid #bfd9ff;
            border-radius: 8px;
            padding: .65rem 1rem;
            font-size: .8rem;
            color: #1e3a5f;
            margin-bottom: 1.25rem;
        }
        .demo-badge strong { display: block; margin-bottom: .2rem; color: #0d6efd; }
        .demo-badge kbd {
            background: #0d6efd; color: #fff;
            border-radius: 4px; padding: .1rem .35rem; font-size: .75rem;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <div class="login-header">
            {{-- Logo: show text fallback if image missing --}}
            @if(file_exists(public_path('assets/img/billtrix-logo-1.png')))
                <img src="{{ asset('assets/img/billtrix-logo-1.png') }}" alt="BillTrix">
            @else
                <h1>💼 BillTrix</h1>
            @endif
            <p>Billing &amp; Accounting SaaS</p>
        </div>

        <div class="login-body">

            {{-- Demo credentials hint --}}
            <div class="demo-badge">
                <strong><i class="fas fa-info-circle me-1"></i>Frontend Preview Mode</strong>
                Use any email &amp; password to enter. No backend required.
                <br>e.g. <kbd>admin@billtrix.com</kbd> / <kbd>password</kbd>
            </div>

            @if($errors->any())
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" name="email" class="form-control"
                               placeholder="admin@billtrix.com"
                               value="{{ old('email', 'admin@billtrix.com') }}" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" class="form-control"
                               placeholder="Enter password" value="password" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>

            </form>
        </div>

        <div class="login-footer">
            &copy; {{ date('Y') }} BillTrix &mdash; Powered by <a href="https://syitrix.com" target="_blank">SyiTrix</a>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

</body>
</html>
