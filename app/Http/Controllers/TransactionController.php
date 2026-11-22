<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\Warehouse;
use App\Services\WithdrawalWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with(['user', 'warehouse', 'items.product'])->latest();
        if ($request->user()->role === 'staff') $query->where('user_id', $request->user()->id);
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                if (preg_match('/\d+/', $search, $matches)) $q->orWhere('id', (int) $matches[0]);
                $q->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$search.'%'));
            });
        }
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        if ($request->filled('from')) $query->whereDate('created_at', '>=', $request->input('from'));
        if ($request->filled('to')) $query->whereDate('created_at', '<=', $request->input('to'));
        if ($request->filled('product')) $query->whereHas('items.product', fn ($q) => $q->where('name', 'like', '%'.$request->input('product').'%'));
        if ($request->filled('warehouse')) $query->where('warehouse_id', $request->input('warehouse'));
        if ($request->filled('anomaly')) $query->whereHas('anomaly');
        return view('transactions.index', ['transactions' => $query->paginate(12)->withQueryString(), 'warehouses' => Warehouse::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('transactions.create', [
            'products' => Product::where('stock', '>', 0)->orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, WithdrawalWorkflow $workflow)
    {
        $data = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'items' => 'required|array|min:1|max:20',
            'items.*.product_id' => 'required|distinct|exists:products,id',
            // A request can contain more than one box for the same product.
            // Each requested SKU is still kept as a distinct row.
            'items.*.quantity' => 'required|integer|min:1',
        ]);
        $transaction = DB::transaction(function () use ($data, $request, $workflow) {
            $products = Product::whereIn('id', collect($data['items'])->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($data['items'] as $item) {
                if ($products[$item['product_id']]->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => "Stok {$products[$item['product_id']]->sku} tidak cukup."]);
                }
            }
            $transaction = Transaction::create(['user_id' => $request->user()->id, 'warehouse_id' => $data['warehouse_id'], 'status' => 'submitted']);
            foreach ($data['items'] as $item) $transaction->items()->create($item);
            $workflow->audit($transaction, $request->user()->id, 'submitted', ['items' => $data['items']]);
            return $transaction;
        });
        return redirect()->route('transactions.show', $transaction)->with('success', 'Permintaan dibuat. Pindai semua SKU sebelum verifikasi kamera.');
    }

    public function show(Request $request, Transaction $transaction)
    {
        abort_if($request->user()->role === 'staff' && (int) $transaction->user_id !== (int) $request->user()->id, 403);
        $transaction->load(['user', 'warehouse', 'items.product', 'scanEvents.product', 'visionEvents.product', 'anomaly.reviewer', 'auditLogs']);
        return view('transactions.show', compact('transaction'));
    }
}
