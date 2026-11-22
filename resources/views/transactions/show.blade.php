@extends('layouts.app')
@section('title', 'Detail transaksi')
@section('heading', 'Verifikasi pengambilan')
@section('subheading', 'Cocokkan SKU, lalu tunggu deteksi kamera checkpoint di pintu keluar.')
@section('content')
@php($expectedScanItems = $transaction->items->count())
@php($scannedItems = $transaction->scanEvents->count())
<section class="panel">
    <div class="split-heading"><div><span class="step-number">TRX-{{ str_pad($transaction->id, 4, '0', STR_PAD_LEFT) }}</span><h3>{{ $transaction->warehouse->name }} · {{ $transaction->user->name }}</h3></div><span class="status {{ in_array($transaction->status, ['review','rejected']) ? 'review' : (in_array($transaction->status, ['submitted','scanned','verifying']) ? 'pending' : '') }}">{{ strtoupper(str_replace('_', ' ', $transaction->status)) }}</span></div>
    <div class="workflow-strip"><span class="done">1 · Permintaan</span><span class="{{ $scannedItems === $expectedScanItems ? 'done' : 'current' }}">2 · Scan SKU</span><span class="{{ $transaction->visionEvents->count() === $transaction->items->count() ? 'done' : 'current' }}">3 · Kamera</span><span class="{{ $transaction->status === 'completed' ? 'done' : 'current' }}">4 · Keputusan</span></div>
    <div class="table-scroll"><table class="table"><thead><tr><th>SKU</th><th>PRODUK</th><th>DIMINTA</th><th>SCAN</th><th>KAMERA</th><th>BUKTI</th></tr></thead><tbody>
        @foreach($transaction->items as $item)
            @php($vision = $transaction->visionEvents->firstWhere('product_id', $item->product_id))
            @php($scannedForItem = $transaction->scanEvents->where('product_id', $item->product_id)->count())
            <tr><td><strong>{{ $item->product->sku }}</strong></td><td>{{ $item->product->name }}</td><td>{{ $item->quantity }} box</td><td>{{ $scannedForItem ? 'Sudah' : 'Belum' }}</td><td>@if($vision)<span>{{ $vision->detected_qty }} box · {{ round($vision->confidence * 100) }}%</span>@if($vision->detected_qty !== $item->quantity)<span class="status review inline-anomaly">SELISIH</span>@endif @else Menunggu @endif</td><td>@if($vision && $vision->evidence_path)<a href="{{ route('evidence.show', $vision) }}" target="_blank" rel="noopener">Lihat bukti</a>@else—@endif</td></tr>
        @endforeach
    </tbody></table></div>
</section>
@if(auth()->user()->role === 'staff' && in_array($transaction->status, ['submitted', 'scanned']))
<div class="action-grid">
    @if($scannedItems < $expectedScanItems)
    <section class="panel"><div class="step-number">02 / BARANG</div><h3>Pindai SKU atau barcode</h3><p class="muted">Pindai satu SKU atau barcode untuk setiap produk. Jumlah box akan diperiksa oleh kamera checkpoint.</p>
        <form method="post" action="{{ route('transactions.scan', $transaction) }}">@csrf<label class="label" for="scan-code">KODE BOX BERIKUTNYA</label><input class="input" id="scan-code" name="code" value="{{ old('code') }}" placeholder="SKU atau barcode" autocomplete="off" required><button class="btn" type="submit">Cocokkan box</button></form>
        <button class="btn secondary camera-button" type="button" id="start-scanner">Buka pemindai kamera</button><video id="scan-video" class="camera-preview" autoplay muted playsinline hidden></video><p id="scan-message" class="muted" role="status"></p>
    </section>
    @endif
</div>
@endif
@if(auth()->user()->role === 'staff' && $transaction->status === 'scanned')
<section class="panel checkpoint-waiting"><div class="step-number">03 / CHECKPOINT KELUAR</div><h3>Menunggu deteksi kamera</h3><p class="muted">Scan sudah lengkap. Bawa barang ke pintu keluar gudang. Kamera checkpoint akan menghitung jumlah barang secara otomatis.</p><p class="muted">Supervisor dan Admin memantau hasil kamera serta menangani transaksi yang memiliki selisih.</p><form method="post" action="{{ route('transactions.demo', $transaction) }}">@csrf<button class="btn btn-primary" type="submit">Demonstrasi</button></form></section>
@endif
@if($transaction->status === 'verifying')<section class="panel checkpoint-waiting"><div class="step-number">03 / CHECKPOINT KELUAR</div><h3>Kamera sedang memproses</h3><p class="muted">Hasil deteksi jumlah barang sedang diproses. Supervisor dan Admin akan melihat hasilnya di transaksi ini.</p></section>@endif
@if($transaction->anomaly)<section class="panel"><div class="step-number">HASIL REVIEW</div><h3>{{ $transaction->anomaly->type === 'insufficient_stock' ? 'Stok berubah' : 'Perlu pemeriksaan Supervisor' }}</h3><p>{{ $transaction->anomaly->description }}</p>@if($transaction->anomaly->reviewer)<p class="muted">{{ $transaction->anomaly->reviewer->name }} · {{ $transaction->anomaly->reviewed_at?->format('d M Y H:i') }} · {{ $transaction->anomaly->review_note }}</p>@endif @if(in_array(auth()->user()->role, ['supervisor', 'admin'], true) && $transaction->status === 'review' && $transaction->anomaly->status === 'pending')<form method="post" action="{{ route('reviews.update', $transaction->anomaly) }}" class="transaction-review-form">@csrf @method('PATCH')<div class="section-kicker">KEPUTUSAN REVIEW</div><label class="label" for="transaction-review-note">CATATAN KEPUTUSAN</label><textarea class="input" id="transaction-review-note" name="note" rows="3" maxlength="500" placeholder="Wajib diisi bila memilih Tidak sesuai">{{ old('note') }}</textarea><div class="review-actions"><button name="decision" value="approve" class="btn approve" type="submit">Accept</button><button name="decision" value="reject" class="btn reject" type="submit">Tidak sesuai</button></div></form>@endif</section>@endif
<section class="panel"><h3>Jejak aktivitas</h3><ol class="audit-list">@foreach($transaction->auditLogs as $log)<li><strong>{{ str_replace('_', ' ', ucfirst($log->action)) }}</strong><span>{{ $log->created_at?->format('d M Y H:i') }}</span></li>@endforeach</ol></section>
@endsection
@push('scripts')
<script>
(() => {
    const button = document.getElementById('start-scanner');
    if (!button) return;
    const video = document.getElementById('scan-video');
    const message = document.getElementById('scan-message');
    let stream;
    button.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
            message.textContent = 'Pemindai kamera tidak tersedia. Ketik SKU atau barcode secara manual.';
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = stream; video.hidden = false; button.disabled = true;
            const detector = new BarcodeDetector();
            const tick = async () => {
                if (!stream) return;
                try { const codes = await detector.detect(video); if (codes.length) {
                    document.getElementById('scan-code').value = codes[0].rawValue;
                    stream.getTracks().forEach(track => track.stop()); stream = null; video.hidden = true; button.disabled = false;
                    message.textContent = 'Kode terbaca. Tekan Cocokkan box untuk menyimpan.';
                    return;
                }} catch (_) {}
                requestAnimationFrame(tick);
            };
            tick();
        } catch (_) { message.textContent = 'Izin kamera tidak tersedia. Ketik kode secara manual.'; }
    });
    window.addEventListener('pagehide', () => stream?.getTracks().forEach(track => track.stop()));
})();
</script>
@endpush
