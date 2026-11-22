<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StockGuard AI · @yield('title', 'Workspace')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/stockguard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('css/figma.css') }}">
    <link rel="stylesheet" href="{{ asset('css/products.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sidebar.css') }}">
    <script>try{const saved=localStorage.getItem('stockguard.sidebar');if(saved==='compact'||(!saved&&innerWidth<780))document.documentElement.classList.add('sidebar-compact')}catch(e){}</script>
</head>
<body class="@yield('body_class')">
<div class="app">
    <aside class="side">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="StockGuard AI, ke Ringkasan">
            <span class="brand-mark"><img src="{{ asset('images/StockGuardlogo.png') }}" alt="" aria-hidden="true"></span>
            <span class="brand-copy"><strong>StockGuard AI</strong><small>WAREHOUSE INTELLIGENCE</small></span>
        </a>
        <div class="side-panel">
        <div class="workspace-head"><span>WORKSPACE</span><button id="sidebar-toggle" type="button" aria-label="Perkecil sidebar" aria-pressed="false" title="Perkecil atau perlebar sidebar"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="3" y="3" width="14" height="14" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M12 3v14M9 7l-2 3 2 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button></div>
        <div class="warehouse-switch"><span class="warehouse-dot"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m12 2.5 8 4.5v10L12 21.5 4 17V7l8-4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m4 7 8 4.5L20 7M12 11.5v10" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg></span><span><strong>Gudang Utama</strong><small>Outbound verification</small></span></div>
        <div class="navlabel operations-label">OPERASIONAL</div>
        <nav class="nav" aria-label="Navigasi utama">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Ringkasan" @if(request()->routeIs('dashboard')) aria-current="page" @endif><span class="nav-icon"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2.5" y="2.5" width="6" height="6" rx=".7"/><rect x="11.5" y="2.5" width="6" height="6" rx=".7"/><rect x="2.5" y="11.5" width="6" height="6" rx=".7"/><rect x="11.5" y="11.5" width="6" height="6" rx=".7"/></svg></span><span>Ringkasan</span></a>
            <a href="{{ route('transactions.index') }}" class="{{ request()->routeIs('transactions.*') ? 'active' : '' }}" title="Transaksi outbound" @if(request()->routeIs('transactions.*')) aria-current="page" @endif><span class="nav-icon"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 6h14m-4-3 4 3-4 3M17 14H3m4-3-4 3 4 3"/></svg></span><span>Transaksi outbound</span></a>
            @if(auth()->user()->role === 'admin')<a href="{{ route('products.index') }}" class="{{ request()->routeIs('products.*') ? 'active' : '' }}" title="Produk & stok" @if(request()->routeIs('products.*')) aria-current="page" @endif><span class="nav-icon"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 7 4v8l-7 4-7-4V6l7-4ZM3 6l7 4 7-4M10 10v8"/></svg></span><span>Produk & stok</span></a>@endif
            @if(auth()->user()->role !== 'staff')<a href="{{ route('checkpoint.index') }}" class="{{ request()->routeIs('checkpoint.*') ? 'active' : '' }}" title="Kamera checkpoint" @if(request()->routeIs('checkpoint.*')) aria-current="page" @endif><span class="nav-icon"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="2" y="4" width="11" height="12" rx="2"/><path d="m13 8 5-2v8l-5-2"/></svg></span><span>Kamera checkpoint</span></a>@endif
        </nav>
        <div class="sidebar-bottom"><div class="sidebar-mantra"><small>TIGA SUMBER. SATU KEPASTIAN.</small><strong>Request → Scan → Vision</strong><span>Setiap pergerakan punya jejak.</span></div><div class="who"><span class="who-name"><b>{{ auth()->user()->name }}</b><small>{{ strtoupper(auth()->user()->role) }}</small></span><form method="post" action="{{ route('logout') }}">@csrf<button type="submit" title="Keluar" aria-label="Keluar"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h3M11 6l4 4-4 4M7 10h8"/></svg></button></form></div></div>
        </div>
    </aside>
    <main class="main">
        <div class="topbar"><div class="breadcrumb">Workspace <span>/</span> <strong>@yield('title', 'Ringkasan')</strong></div><div class="topbar-meta"><span>RESEARCH PROTOTYPE</span><i></i><time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('d M Y') }}</time>@if(auth()->user()->role !== 'staff')<details class="workspace-tools"><summary title="Menu lainnya" aria-label="Menu lainnya">⋯</summary><div class="workspace-tools-menu">@if(auth()->user()->role !== 'staff')<a href="{{ route('reviews.index') }}">Pending review</a>@endif @if(auth()->user()->role === 'admin')<a href="{{ route('users.index') }}">Pengguna</a><a href="{{ route('warehouses.index') }}">Gudang</a><a href="{{ route('audits.index') }}">Audit trail</a>@endif</div></details>@endif</div></div>
        <header class="head"><div><h1>@yield('heading', 'Ringkasan operasional')</h1><p>@yield('subheading', 'Pantau barang keluar. Temukan selisih sebelum terlewat.')</p></div>@yield('header_action')</header>
        @if(session('success'))<div class="success" role="status">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="warning" role="alert">{{ session('warning') }}</div>@endif
        @if($errors->any())<div class="error-box" role="alert"><strong>Periksa kembali data:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
@stack('scripts')
<script>
(() => {
    const toggle = document.getElementById('sidebar-toggle');
    if (!toggle) return;
    const update = () => {
        const compact = document.documentElement.classList.contains('sidebar-compact');
        toggle.setAttribute('aria-pressed', compact ? 'true' : 'false');
        toggle.setAttribute('aria-label', compact ? 'Perlebar sidebar' : 'Perkecil sidebar');
    };
    toggle.addEventListener('click', () => {
        document.documentElement.classList.toggle('sidebar-compact');
        try { localStorage.setItem('stockguard.sidebar', document.documentElement.classList.contains('sidebar-compact') ? 'compact' : 'expanded'); } catch (e) {}
        update();
    });
    window.addEventListener('resize', () => {
        try { if (!localStorage.getItem('stockguard.sidebar')) document.documentElement.classList.toggle('sidebar-compact', innerWidth < 780); } catch (e) {}
        update();
    });
    update();
})();
</script>
</body>
</html>
