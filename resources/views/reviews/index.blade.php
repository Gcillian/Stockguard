@extends('layouts.app')
@section('title', 'Pending review')
@section('heading', 'Pending review')
@section('subheading', 'Bandingkan permintaan dengan hasil deteksi kamera pintu keluar sebelum mengambil keputusan.')
@section('content')
<section class="panel"><div class="step-number">ANTRIAN SUPERVISOR</div><h3>{{ $anomalies->count() }} anomali menunggu keputusan</h3>
    @forelse($anomalies as $anomaly)
    <article class="review-card">
        <div><div class="step-number">TRX-{{ str_pad($anomaly->transaction->id, 4, '0', STR_PAD_LEFT) }} · {{ strtoupper($anomaly->type) }}</div><h3><a href="{{ route('transactions.show', $anomaly->transaction) }}">{{ $anomaly->transaction->user->name }} · {{ $anomaly->transaction->warehouse->name }}</a></h3><p>{{ $anomaly->description }}</p>
            <div class="review-facts">@foreach($anomaly->transaction->items as $item)@php($vision = $anomaly->transaction->visionEvents->firstWhere('product_id', $item->product_id))<div><b>{{ $item->product->sku }}</b><span>Diminta {{ $item->quantity }} · Terdeteksi {{ $vision?->detected_qty ?? '—' }} · Confidence {{ $vision ? round($vision->confidence * 100).'%' : '—' }}</span>@if($vision?->evidence_path)<a href="{{ route('evidence.show', $vision) }}" target="_blank" rel="noopener">Lihat foto bukti ↗</a>@endif</div>@endforeach</div>
        </div>
        <form method="post" action="{{ route('reviews.update', $anomaly) }}" class="review-form">@csrf @method('PATCH')<label class="label">CATATAN KEPUTUSAN</label><textarea class="input" name="note" rows="3" maxlength="500" placeholder="Wajib diisi bila menolak">{{ old('note') }}</textarea><div class="review-actions"><button name="decision" value="approve" class="btn approve" type="submit">Setujui</button><button name="decision" value="reject" class="btn reject" type="submit">Tolak</button></div></form>
    </article>
    @empty<p class="muted">Tidak ada anomali yang menunggu review.</p>@endforelse
</section>
@endsection
