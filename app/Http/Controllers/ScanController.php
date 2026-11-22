<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Services\WithdrawalWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScanController extends Controller
{
    public function store(Request $request, Transaction $transaction, WithdrawalWorkflow $workflow)
    {
        abort_if($request->user()->role === 'staff' && (int) $transaction->user_id !== (int) $request->user()->id, 403);
        $data = $request->validate(['code' => 'required|string|max:100']);
        $warningMessage = null;
        $mismatchMessage = null;
        DB::transaction(function () use ($transaction, $request, $data, $workflow, &$warningMessage, &$mismatchMessage) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);
            if (!in_array($transaction->status, ['submitted', 'scanned'], true)) {
                throw ValidationException::withMessages(['code' => 'Transaksi ini tidak dapat dipindai lagi.']);
            }
            // One SKU/barcode scan represents the requested product row. The
            // quantity is checked later by the exit checkpoint camera.
            $expectedItems = (int) $transaction->items()->count();
            $product = Product::where('sku', $data['code'])->orWhere('barcode', $data['code'])->first();
            if (!$product || !$transaction->items()->where('product_id', $product->id)->exists()) {
                $workflow->flagScanMismatch($transaction, $request->user()->id, "Kode {$data['code']} tidak termasuk dalam permintaan transaksi.", ['code' => $data['code']]);
                $transaction->update(['status' => 'review']);
                $mismatchMessage = 'SKU atau barcode tidak cocok dengan permintaan. Transaksi masuk Pending review.';
                return;
            }
            // A repeated code is a recoverable input mistake. Do not create an
            // anomaly or stop the request; ask Staff to enter the next code.
            if ($transaction->scanEvents()->where('product_id', $product->id)->exists()) {
                $warningMessage = "SKU {$product->sku} sudah dipindai. Masukkan kode SKU/barcode box berikutnya.";
                return;
            }
            $transaction->scanEvents()->create(['product_id' => $product->id, 'scanned_code' => $data['code'], 'scanned_at' => now()]);
            $workflow->audit($transaction, $request->user()->id, 'item_scanned', ['product_id' => $product->id, 'code' => $data['code']]);
            if ($transaction->scanEvents()->count() === $expectedItems) $transaction->update(['status' => 'scanned']);
        });
        if ($warningMessage) return back()->with('warning', $warningMessage);
        if ($mismatchMessage) throw ValidationException::withMessages(['code' => $mismatchMessage]);
        return back()->with('success', 'Barang berhasil dipindai.');
    }
}
