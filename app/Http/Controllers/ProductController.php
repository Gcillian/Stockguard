<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\WithdrawalWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['latestStockMovement.transaction', 'latestStockAdjustment'])->orderBy('name');
        $transactionId = null;
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $transactionId = preg_match('/^(?:OUT-|TRX-)?(\d+)$/i', $search, $matches) ? (int) $matches[1] : null;
            $query->where(function ($q) use ($search, $transactionId) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%');
                if ($transactionId) {
                    $q->orWhereHas('stockMovements', fn ($movement) => $movement->where('transaction_id', $transactionId));
                }
            });
        }
        $products = $query->paginate(15)->withQueryString();
        if ($transactionId) {
            $products->getCollection()->load(['stockMovements' => fn ($movement) => $movement->where('transaction_id', $transactionId)->with('transaction')]);
        }
        return view('products.index', ['products' => $products, 'productCount' => Product::count(), 'searchedTransactionId' => $transactionId]);
    }

    public function store(Request $request, WithdrawalWorkflow $workflow)
    {
        $data = $request->validate(['sku' => 'required|string|max:50|unique:products,sku', 'barcode' => 'required|string|max:100|unique:products,barcode', 'name' => 'required|string|max:150', 'stock' => 'required|integer|min:0']);
        DB::transaction(function () use ($data, $request, $workflow) {
            $product = Product::create($data);
            if ($product->stock > 0) {
                StockAdjustment::create(['product_id' => $product->id, 'user_id' => $request->user()->id, 'quantity' => $product->stock, 'stock_after' => $product->stock, 'reason' => 'Stok awal', 'created_at' => now()]);
            }
            $workflow->audit(null, $request->user()->id, 'product_created', ['product_id' => $product->id, 'sku' => $product->sku, 'stock' => $product->stock]);
        });
        return back()->with('success', 'Produk ditambahkan.');
    }

    public function update(Request $request, Product $product, WithdrawalWorkflow $workflow)
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product->id)],
            'barcode' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($product->id)],
            'name' => 'required|string|max:150',
            'stock' => 'required|integer|min:0',
            'reason' => 'required|string|max:500',
        ]);
        DB::transaction(function () use ($product, $data, $request, $workflow) {
            $product = Product::lockForUpdate()->findOrFail($product->id);
            $before = $product->only(['sku', 'barcode', 'name', 'stock']);
            $product->update(collect($data)->only(['sku', 'barcode', 'name', 'stock'])->all());
            $difference = $product->stock - $before['stock'];
            if ($difference !== 0) {
                StockAdjustment::create(['product_id' => $product->id, 'user_id' => $request->user()->id, 'quantity' => $difference, 'stock_after' => $product->stock, 'reason' => $data['reason'], 'created_at' => now()]);
            }
            $workflow->audit(null, $request->user()->id, 'product_updated', ['product_id' => $product->id, 'before' => $before, 'after' => $product->only(['sku', 'barcode', 'name', 'stock']), 'reason' => $data['reason']]);
        });
        return back()->with('success', 'Produk diperbarui.');
    }
}
