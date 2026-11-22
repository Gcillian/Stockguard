@extends('layouts.app')
@section('title', 'Kamera checkpoint')
@section('heading', 'Kamera checkpoint')
@section('subheading', 'Pantau kamera di pintu keluar dan hasil deteksi jumlah barang.')
@section('content')
<div class="checkpoint-layout">
    <section class="panel camera-workspace"><div class="split-heading"><div><div class="section-kicker">EXIT CHECKPOINT / LIVE MONITORING</div><h2>Kamera pintu keluar</h2></div><span class="status pending" id="camera-status">BELUM TERHUBUNG</span></div>
        <div class="camera-stage" id="camera-stage"><video id="checkpoint-video" autoplay muted playsinline hidden></video><div class="camera-empty" id="camera-empty"><span class="camera-big">◎</span><strong>Siapkan sudut pandang Anda</strong><p>Hubungkan kamera untuk melihat area verifikasi.</p></div><div class="guide-line" id="guide-line" hidden></div></div>
        <div class="camera-controls"><div class="camera-select-row"><label class="label" for="camera-source">SUMBER KAMERA</label><button type="button" class="text-link" id="reload-cameras">↻ Muat ulang daftar</button></div><select class="input" id="camera-source"><option value="">Pilih kamera yang tersedia</option></select><button class="btn btn-primary camera-connect" type="button" id="start-camera">◎ Aktifkan kamera</button><label class="label range-label" for="line-position">POSISI GARIS DETEKSI <span id="line-value">50%</span></label><input type="range" id="line-position" min="10" max="90" value="50"><p class="muted" id="camera-message" role="status">Kamera perangkat hanya aktif setelah Anda mengizinkannya. Hasil hitung dikirim ke alur verifikasi transaksi.</p></div>
    </section>
    <div class="checkpoint-aside"><section class="panel"><div class="section-kicker">ALUR PENGAWASAN</div><h2>Tiga langkah terhubung</h2><div class="aside-step"><span>01</span><div><strong>Staff scan barcode</strong><p>Identitas barang dicocokkan dengan permintaan.</p></div></div><div class="aside-step"><span>02</span><div><strong>Barang melewati pintu keluar</strong><p>Kamera membaca jumlah box yang dibawa.</p></div></div><div class="aside-step"><span>03</span><div><strong>Supervisor/Admin review</strong><p>Selisih jumlah ditandai untuk ditindaklanjuti.</p></div></div><a class="btn btn-primary" href="{{ route('transactions.index') }}">Lihat transaksi →</a></section><section class="panel record-promo"><span class="section-kicker">AUTOMATED COUNTING</span><h2>Deteksi jumlah barang</h2><p>Kamera checkpoint menjadi sumber bukti visual. Staff tidak perlu mengambil foto atau mengoperasikan kamera.</p><span class="promo-icon">▣ ◎ ✓</span></section></div>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const video = document.getElementById('checkpoint-video');
    const empty = document.getElementById('camera-empty');
    const select = document.getElementById('camera-source');
    const status = document.getElementById('camera-status');
    const message = document.getElementById('camera-message');
    const guide = document.getElementById('guide-line');
    const start = document.getElementById('start-camera');
    let stream;
    async function loadCameras() {
        if (!navigator.mediaDevices?.enumerateDevices) {
            message.textContent = 'Browser ini tidak mendukung akses kamera.';
            return;
        }
        const devices = (await navigator.mediaDevices.enumerateDevices()).filter(d => d.kind === 'videoinput');
        select.innerHTML = '<option value="">Kamera default</option>' + devices.map((d, i) => '<option value="' + d.deviceId.replaceAll('"', '&quot;') + '">' + (d.label || 'Kamera ' + (i + 1)) + '</option>').join('');
    }
    document.getElementById('reload-cameras').addEventListener('click', loadCameras);
    start.addEventListener('click', async () => {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
            video.hidden = true;
            empty.hidden = false;
            guide.hidden = true;
            status.textContent = 'BELUM TERHUBUNG';
            status.className = 'status pending';
            start.textContent = '◎ Aktifkan kamera';
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({video: select.value ? {deviceId: {exact: select.value}} : {facingMode: 'environment'}, audio: false});
            video.srcObject = stream;
            video.hidden = false;
            empty.hidden = true;
            guide.hidden = false;
            status.textContent = 'TERHUBUNG';
            status.className = 'status';
            start.textContent = 'Putuskan kamera';
            message.textContent = 'Kamera aktif untuk pemantauan pintu keluar. Penghitungan barang diproses oleh alur deteksi.';
            await loadCameras();
        } catch (_) {
            message.textContent = 'Kamera gagal diaktifkan. Periksa izin perangkat dan gunakan HTTPS atau localhost.';
        }
    });
    document.getElementById('line-position').addEventListener('input', event => {
        guide.style.top = event.target.value + '%';
        document.getElementById('line-value').textContent = event.target.value + '%';
    });
    window.addEventListener('pagehide', () => stream?.getTracks().forEach(track => track.stop()));
    loadCameras();
})();
</script>
@endpush
