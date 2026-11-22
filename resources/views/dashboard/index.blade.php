@extends('layouts.app')
@section('title', 'Ringkasan')
@section('header_action')
    @if($user->role === 'staff')<a class="btn btn-primary" href="{{ route('transactions.create') }}">＋ Transaksi baru</a>@endif
@endsection
@section('content')
<div class="metrics">
    <div class="metric metric-featured"><span class="metric-icon">↗</span><span class="label">TRANSAKSI HARI INI</span><div class="value">{{ $metrics['transactions'] }}</div><span class="metric-caption">Permintaan barang keluar hari ini</span></div>
    <div class="metric"><span class="metric-icon">✓</span><span class="label">TERVERIFIKASI</span><div class="value">{{ $metrics['verified'] }}</div><span class="metric-caption">Selesai melalui tiga lapis verifikasi</span></div>
    <div class="metric"><span class="metric-icon">◇</span><span class="label">PERLU REVIEW</span><div class="value">{{ $metrics['anomalies'] }}</div><span class="metric-caption">Anomali menunggu keputusan</span></div>
    @if($user->role !== 'staff')<div class="metric"><span class="metric-icon">◎</span><span class="label">CHECKPOINT ONLINE</span><div class="value">{{ $metrics['cameras_online'] }}</div><span class="metric-caption">Kamera aktif di gudang</span></div>@endif
</div>
<div class="dashboard-columns {{ $user->role === 'staff' ? 'dashboard-staff' : '' }}">
    <section class="panel verification-panel">
        <div class="section-kicker">CARA KERJA STOCKGUARD</div><h2>Tiga lapis verifikasi</h2><p class="muted">Setiap barang keluar diverifikasi dari permintaan hingga bukti visual.</p>
        <div class="verification-steps">
            <div class="verification-step"><span class="step-badge">01</span><span class="step-symbol">▤</span><div><strong>Request</strong><p>Catat barang dan jumlah yang akan diambil.</p></div></div>
            <div class="verification-step"><span class="step-badge">02</span><span class="step-symbol">▦</span><div><strong>Barcode scan</strong><p>Pastikan identitas barang sesuai permintaan.</p></div></div>
            <div class="verification-step"><span class="step-badge">03</span><span class="step-symbol">◎</span><div><strong>Exit camera</strong><p>Kamera pintu keluar menghitung jumlah fisik barang.</p></div></div>
        </div>
    </section>
    @if($user->role !== 'staff')<section class="panel checkpoint-panel"><div class="split-heading"><div><div class="section-kicker">LIVE MONITORING</div><h2>Checkpoint status</h2></div><a class="text-link" href="{{ route('checkpoint.index') }}">Lihat kamera →</a></div><div class="checkpoint-list">@forelse($cameras as $camera)<div class="checkpoint-row"><span class="camera-glyph">◎</span><span><strong>{{ $camera->name }}</strong><small>{{ $camera->warehouse?->name }}</small></span><span class="status {{ $camera->status === 'online' ? '' : 'review' }}">{{ strtoupper($camera->status) }}</span></div>@empty<div class="empty-note">Belum ada kamera checkpoint terdaftar.</div>@endforelse</div></section>@endif
</div>
<section class="panel activity-panel"><div class="split-heading"><div><div class="section-kicker">JEJAK OPERASIONAL</div><h2>Aktivitas terbaru</h2></div><a class="text-link" href="{{ route('transactions.index') }}">Semua transaksi →</a></div><div class="table-scroll"><table class="table"><thead><tr><th>TRANSAKSI</th><th>PEMOHON</th><th>GUDANG</th><th>STATUS</th><th>WAKTU</th></tr></thead><tbody>@forelse($recent as $tx)<tr><td><a class="transaction-link" href="{{ route('transactions.show', $tx) }}">OUT-{{ str_pad($tx->id, 4, '0', STR_PAD_LEFT) }}</a></td><td>{{ $tx->user->name }}</td><td>{{ $tx->warehouse->name }}</td><td><span class="status {{ in_array($tx->status, ['review','rejected']) ? 'review' : (in_array($tx->status, ['submitted','scanned','verifying']) ? 'pending' : '') }}">{{ strtoupper($tx->status) }}</span></td><td>{{ $tx->created_at->format('d M Y, H:i') }}</td></tr>@empty<tr><td colspan="5" class="muted">Belum ada transaksi.</td></tr>@endforelse</tbody></table></div></section>
@endsection
