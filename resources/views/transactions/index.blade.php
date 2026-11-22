@extends('layouts.app')
@section('title', 'Transaksi outbound')
@section('heading', 'Transaksi outbound')
@section('subheading', 'Lacak setiap permintaan barang keluar dan status verifikasinya.')
@section('header_action')
    @if(auth()->user()->role === 'staff')<a class="btn btn-primary" href="{{ route('transactions.create') }}">＋ Transaksi baru</a>@endif
@endsection
@section('content')
<section class="panel listing-panel"><div class="split-heading"><div><div class="section-kicker">TRANSAKSI BARANG KELUAR</div><h2>Semua transaksi</h2></div><span class="count-pill">{{ $transactions->total() }} transaksi</span></div>
    <form method="get" action="{{ route('transactions.index') }}" class="filter-grid">
        <label class="search-field"><span>⌕</span><input name="search" value="{{ request('search') }}" placeholder="Cari kode transaksi atau petugas"></label>
        <select name="status" aria-label="Status"><option value="">Semua status</option>@foreach(['submitted'=>'Submitted','scanned'=>'Scanned','verifying'=>'Verifying','completed'=>'Completed','review'=>'Perlu review','rejected'=>'Rejected'] as $key=>$label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select>
        <input type="date" name="from" value="{{ request('from') }}" aria-label="Dari tanggal">
        <input type="date" name="to" value="{{ request('to') }}" aria-label="Sampai tanggal">
        <input name="product" value="{{ request('product') }}" placeholder="Nama produk" aria-label="Nama produk">
        <select name="warehouse" aria-label="Gudang"><option value="">Semua gudang</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) request('warehouse') === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select>
        <select name="anomaly" aria-label="Anomali"><option value="">Semua anomali</option><option value="1" @selected(request('anomaly'))>Ada anomali</option></select>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Terapkan</button><a class="text-link" href="{{ route('transactions.index') }}">Reset filter</a></div>
    </form>
    <div class="table-scroll"><table class="table transaction-table"><thead><tr><th>TRANSAKSI</th><th>WAKTU DIBUAT</th><th>PERCOBAAN / ITEM</th><th>STATUS</th><th>AKSI</th></tr></thead><tbody>@forelse($transactions as $tx)<tr><td><div class="entity-cell"><span class="entity-icon">▣</span><span><a class="transaction-link" href="{{ route('transactions.show', $tx) }}">OUT-{{ str_pad($tx->id, 4, '0', STR_PAD_LEFT) }}</a><small>{{ $tx->user->name }} · {{ $tx->warehouse->name }}</small></span></div></td><td>{{ $tx->created_at->format('d M Y') }}<small class="cell-subline">{{ $tx->created_at->format('H:i') }}</small></td><td>{{ $tx->items->sum('quantity') }} box <small class="cell-subline">{{ $tx->items->count() }} produk</small></td><td><span class="status {{ in_array($tx->status, ['review','rejected']) ? 'review' : (in_array($tx->status, ['submitted','scanned','verifying']) ? 'pending' : '') }}">{{ strtoupper($tx->status) }}</span></td><td><a class="row-action" href="{{ route('transactions.show', $tx) }}">Lihat detail →</a></td></tr>@empty<tr><td colspan="5" class="muted">Belum ada transaksi yang cocok.</td></tr>@endforelse</tbody></table></div><div class="pagination">{{ $transactions->links() }}</div>
</section>
@endsection
