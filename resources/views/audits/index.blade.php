@extends('layouts.app')
@section('title', 'Audit trail')
@section('heading', 'Audit trail')
@section('subheading', 'Riwayat perubahan transaksi dan master data.')
@section('content')
<section class="panel"><div class="step-number">RIWAYAT SISTEM</div><h3>Aktivitas terbaru</h3><div class="table-scroll"><table class="table"><thead><tr><th>WAKTU</th><th>PENGGUNA</th><th>AKSI</th><th>TRANSAKSI</th><th>DETAIL</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at?->format('d M Y H:i') }}</td><td>{{ $log->user?->name ?? '—' }}</td><td><b>{{ str_replace('_', ' ', strtoupper($log->action)) }}</b></td><td>@if($log->transaction)<a href="{{ route('transactions.show', $log->transaction) }}">TRX-{{ str_pad($log->transaction_id, 4, '0', STR_PAD_LEFT) }}</a>@else—@endif</td><td><code>{{ \Illuminate\Support\Str::limit(json_encode($log->details, JSON_UNESCAPED_UNICODE), 100) }}</code></td></tr>@empty<tr><td colspan="5">Belum ada aktivitas.</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $logs->links() }}</div></section>
@endsection
