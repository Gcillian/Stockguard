<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · StockGuard AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/figma.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
</head>
<body class="login-body">
<div class="auth-tab">Login</div>
<main class="auth-card">
    <section class="auth-form-side">
        <div class="auth-brand"><span class="auth-brand-mark"><img src="{{ asset('images/StockGuardlogo.png') }}" alt="" aria-hidden="true"></span><span><strong>STOCKGUARD AI</strong><small>WAREHOUSE VERIFICATION</small></span></div>
        <div class="auth-content"><div class="section-kicker">EVIDENCE FIRST. ALWAYS.</div><h1>Login</h1><p>Lanjutkan memantau setiap pergerakan barang.</p>
            @if($errors->any())<div class="auth-error" role="alert">{{ $errors->first() }}</div>@endif
            <form method="post" action="{{ route('login.submit') }}">@csrf
                <label for="email">Email atau Username</label><input id="email" name="email" type="text" value="{{ old('email') }}" autocomplete="username" placeholder="Masukkan email atau nama pengguna" required autofocus>
                <label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required>
                <div class="auth-options"><label class="remember"><input type="checkbox" name="remember" value="1"> Ingat saya</label><span class="forgot-hint" title="Hubungi admin gudang untuk mengatur ulang password">Lupa kata sandi?</span></div>
                <div class="auth-actions"><button class="auth-submit" type="submit">Sign in</button><a class="auth-back" href="{{ url('/') }}">Back</a></div>
            </form>
        </div>
        <div class="auth-footer">© {{ date('Y') }} StockGuard AI · Warehouse intelligence</div>
    </section>
    <section class="auth-visual-side" aria-label="Ilustrasi operasional gudang"><div class="auth-visual-art"><div class="art-grid"></div><div class="art-rack rack-one"><i></i><i></i><i></i></div><div class="art-rack rack-two"><i></i><i></i><i></i></div><div class="art-rack rack-three"><i></i><i></i><i></i></div><div class="art-console"><span>STOCKGUARD / LIVE</span><div class="art-console-top"><b>Outbound verification</b><em>● LIVE</em></div><div class="art-bars"><i></i><i></i><i></i><i></i></div><div class="art-console-foot">REQUEST <b>✓</b> SCAN <b>✓</b> VISION <b>◎</b></div></div></div><div class="auth-visual-caption"><span>REQUEST / SCAN / VISION</span><h2>Satu langkah menuju gudang yang lebih pasti.</h2><p>Setiap pergerakan barang punya jejak yang dapat dipercaya.</p></div></section>
</main>
</body>
</html>
