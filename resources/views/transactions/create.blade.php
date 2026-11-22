@extends('layouts.app')
@section('title', 'Buat transaksi')
@section('heading', 'Permintaan pengambilan')
@section('subheading', 'Pilih gudang dan barang yang akan dibawa keluar.')
@section('content')
<section class="panel form">
    <div class="step-number">01 / PERMINTAAN</div>
    <h3>Barang yang akan diambil</h3>
    <p class="muted">Pilih SKU dan jumlah box yang akan diambil. Setelah semua box di-scan, bawa barang ke pintu keluar. Kamera checkpoint akan menghitung jumlah fisik; stok berkurang setelah hasil kamera cocok atau Supervisor menyetujui anomali.</p>
    <form method="post" action="{{ route('transactions.store') }}" id="request-form">
        @csrf
        <label class="label" for="warehouse_id">GUDANG</label>
        <select class="input" id="warehouse_id" name="warehouse_id" required>
            @foreach($warehouses as $warehouse)
                <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }} · {{ $warehouse->location }}</option>
            @endforeach
        </select>
        <div id="item-rows">
            @foreach(old('items', [['product_id' => '', 'quantity' => 1]]) as $i => $oldItem)
            <div class="item-row" data-row>
                <div><label class="label">PRODUK / SKU</label><select class="input" name="items[{{ $i }}][product_id]" required>
                    <option value="">Pilih produk</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected(($oldItem['product_id'] ?? '') == $product->id)>{{ $product->name }} · {{ $product->sku }} · stok {{ $product->stock }}</option>
                    @endforeach
                </select></div>
                <div><label class="label">JUMLAH BOX</label><input class="input" type="number" min="1" name="items[{{ $i }}][quantity]" value="{{ $oldItem['quantity'] ?? 1 }}" required></div>
                <button class="text-button" type="button" data-remove aria-label="Hapus produk">Hapus</button>
            </div>
            @endforeach
        </div>
        <button class="btn secondary" type="button" id="add-item">+ Tambah box</button>
        <div class="form-actions"><a class="btn secondary" href="{{ route('transactions.index') }}">Batal</a><button class="btn" type="submit">Buat permintaan →</button></div>
    </form>
</section>
<template id="item-template"><div class="item-row" data-row>
    <div><label class="label">PRODUK / SKU</label><select class="input" name="items[__INDEX__][product_id]" required><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} · {{ $product->sku }} · stok {{ $product->stock }}</option>@endforeach</select></div>
    <div><label class="label">JUMLAH BOX</label><input class="input" type="number" min="1" name="items[__INDEX__][quantity]" value="1" required></div>
    <button class="text-button" type="button" data-remove aria-label="Hapus produk">Hapus</button>
</div></template>
@endsection
@push('scripts')
<script>
(() => {
    const rows = document.getElementById('item-rows');
    let next = rows.querySelectorAll('[data-row]').length;
    document.getElementById('add-item').addEventListener('click', () => {
        if (rows.querySelectorAll('[data-row]').length >= 20) return;
        rows.insertAdjacentHTML('beforeend', document.getElementById('item-template').innerHTML.replaceAll('__INDEX__', next++));
    });
    rows.addEventListener('click', event => {
        if (event.target.matches('[data-remove]') && rows.querySelectorAll('[data-row]').length > 1) event.target.closest('[data-row]').remove();
    });
})();
</script>
@endpush
