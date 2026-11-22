@extends('layouts.app')
@section('body_class', 'products-page')
@section('title', 'Produk & stok')
@section('heading', 'Stock Movement')
@section('subheading', 'Identitas produk dan stok yang dapat ditelusuri.')
@section('content')
<section class="panel inventory-panel" aria-labelledby="inventory-title">
    <div class="inventory-toolbar">
        <div class="inventory-title"><h2 id="inventory-title">Master produk</h2><span class="count-pill">{{ $productCount }} produk</span></div>
        <div class="inventory-actions"><form method="get" action="{{ route('products.index') }}" role="search"><label class="sr-only" for="product-search">Cari ID transaksi atau produk</label><input id="product-search" name="search" type="search" value="{{ request('search') }}" placeholder="Cari ID Transaksi atau Product..." aria-label="Cari ID Transaksi atau Product"><button type="submit" aria-label="Cari">⌕</button></form><button class="inventory-add" type="button" onclick="document.getElementById('new-product').showModal()">+ Produk</button></div>
    </div>
    @if(request('search'))<div class="search-summary">{{ $products->total() }} hasil untuk “{{ request('search') }}” <a href="{{ route('products.index') }}">Hapus pencarian</a></div>@endif
    <div class="table-scroll"><table class="table inventory-table"><thead><tr><th scope="col">PRODUK</th><th scope="col">SKU / BARCODE</th><th scope="col">WAKTU</th><th scope="col">ID TRANSAKSI</th><th scope="col">KUANTITAS</th><th scope="col">STOK</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead><tbody>
        @forelse($products as $product)
            @php
                $movement = $product->latestStockMovement;
                $adjustment = $product->latestStockAdjustment;
                $matchedMovement = $searchedTransactionId ? $product->stockMovements->first() : null;
                $latest = $matchedMovement ?: ($movement && $adjustment
                    ? ($movement->created_at->greaterThanOrEqualTo($adjustment->created_at) ? $movement : $adjustment)
                    : ($movement ?? $adjustment));
                $isTransaction = $latest instanceof \App\Models\StockMovement;
                $palette = ['peach', 'blue', 'yellow', 'green', 'gray'][$loop->index % 5];
            @endphp
            <tr>
                <td><div class="inventory-product"><span class="inventory-cube {{ $palette }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m12 2 8.5 4.7v10.6L12 22l-8.5-4.7V6.7L12 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="m3.5 6.7 8.5 4.8 8.5-4.8M12 11.5V22M8 4.2l8.5 4.9" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg></span><button class="product-name" type="button" onclick="document.getElementById('product-{{ $product->id }}').showModal()" title="Kelola {{ $product->name }}">{{ $product->name }}</button></div></td>
                <td><span class="sku-line">{{ $product->sku }} · {{ $product->barcode }}</span></td>
                <td>{{ $latest?->created_at?->format('Y-m-d H:i') ?? $product->updated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                <td>@if($isTransaction && $latest->transaction)<a class="inventory-transaction" href="{{ route('transactions.show', $latest->transaction) }}">{{ $latest->transaction_id }}</a>@else<span class="manual-label">—</span>@endif</td>
                <td>@if($latest && $latest->quantity !== 0)<span class="quantity-badge {{ $latest->quantity > 0 ? 'positive' : 'negative' }}">{{ $latest->quantity > 0 ? '+' : '' }}{{ $latest->quantity }}</span>@else<span class="quantity-none">—</span>@endif</td>
                <td><strong class="inventory-stock">{{ $product->stock }}</strong><span class="inventory-unit">unit</span></td>
                <td><button class="inventory-edit" type="button" onclick="document.getElementById('product-{{ $product->id }}').showModal()" aria-label="Kelola {{ $product->name }}">⋯</button></td>
            </tr>
        @empty
            <tr><td colspan="7" class="inventory-empty">Produk tidak ditemukan. @if(request('search'))<a href="{{ route('products.index') }}">Tampilkan semua</a>@endif</td></tr>
        @endforelse
    </tbody></table></div>
    @if($products->hasPages())<div class="pagination">{{ $products->links() }}</div>@endif
</section>
<p class="inventory-note">Stok diperbarui setelah transaksi terverifikasi atau disetujui supervisor.</p>
<footer class="inventory-footer"><span>STOCKGUARD AI / WAREHOUSE VERIFICATION</span><span>Evidence first. Always.</span></footer>
<dialog id="new-product" class="edit-dialog inventory-dialog"><form method="post" action="{{ route('products.store') }}">@csrf<div class="section-kicker">MASTER PRODUK</div><h3>Tambah produk</h3><label class="label">SKU<input class="input" name="sku" value="{{ old('sku') }}" required></label><label class="label">BARCODE<input class="input" name="barcode" value="{{ old('barcode') }}" required></label><label class="label">NAMA PRODUK<input class="input" name="name" value="{{ old('name') }}" required></label><label class="label">STOK AWAL<input class="input" name="stock" type="number" min="0" value="{{ old('stock', 0) }}" required></label><div class="review-actions"><button class="btn secondary" type="button" onclick="this.closest('dialog').close()">Batal</button><button class="btn btn-primary" type="submit">Simpan produk</button></div></form></dialog>
@foreach($products as $product)<dialog id="product-{{ $product->id }}" class="edit-dialog inventory-dialog"><form method="post" action="{{ route('products.update', $product) }}">@csrf @method('PUT')<div class="section-kicker">KELOLA PRODUK & STOK</div><h3>{{ $product->name }}</h3><label class="label">SKU<input class="input" name="sku" value="{{ $product->sku }}" required></label><label class="label">BARCODE<input class="input" name="barcode" value="{{ $product->barcode }}" required></label><label class="label">NAMA<input class="input" name="name" value="{{ $product->name }}" required></label><label class="label">STOK SAAT INI<input class="input" name="stock" type="number" min="0" value="{{ $product->stock }}" required></label><label class="label">ALASAN PERUBAHAN<textarea class="input" name="reason" rows="2" maxlength="500" placeholder="Contoh: hasil stok opname" required></textarea></label><div class="review-actions"><button class="btn secondary" type="button" onclick="this.closest('dialog').close()">Batal</button><button class="btn btn-primary" type="submit">Simpan perubahan</button></div></form></dialog>@endforeach
@endsection
