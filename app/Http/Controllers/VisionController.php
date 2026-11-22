<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessVisionCapture;
use App\Models\Transaction;
use App\Models\VisionEvent;
use App\Services\WithdrawalWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VisionController extends Controller
{
    public function demo(Request $request, Transaction $transaction, WithdrawalWorkflow $workflow)
    {
        abort_if((int) $transaction->user_id !== (int) $request->user()->id, 403);
        $transaction->load(['items.product']);
        $expectedItems = (int) $transaction->items->count();
        if ($transaction->status !== 'scanned' || $transaction->scanEvents()->count() !== $expectedItems) {
            throw ValidationException::withMessages(['demo' => 'Pindai semua SKU sebelum menjalankan demonstrasi checkpoint.']);
        }
        $demoFile = storage_path('app/demo/4boxdetected.jpeg');
        if (!is_file($demoFile)) {
            throw ValidationException::withMessages(['demo' => 'Aset 4boxdetected.jpeg belum tersedia.']);
        }
        $transaction->update(['status' => 'verifying']);

        $path = "evidence/{$transaction->id}/4boxdetected.jpeg";
        if (!Storage::disk('local')->put($path, file_get_contents($demoFile))) {
            throw ValidationException::withMessages(['demo' => 'Gambar demonstrasi gagal disimpan.']);
        }

        $result = $workflow->recordDemoCheckpoint(
            $transaction->id,
            4,
            .99,
            $path,
            (int) $request->user()->id
        );

        $message = match ($result) {
            'completed' => 'Demonstrasi berhasil: kamera mendeteksi 4 box dan transaksi selesai.',
            'review' => 'Demonstrasi selesai: kamera mendeteksi 4 box, tetapi jumlah permintaan berbeda. Transaksi masuk Pending review.',
            default => 'Hasil demonstrasi disimpan.',
        };

        return redirect()->route('transactions.show', $transaction)->with('success', $message);
    }

    public function store(Request $request, Transaction $transaction, WithdrawalWorkflow $workflow)
    {
        abort_if($request->user()->role === 'staff' && (int) $transaction->user_id !== (int) $request->user()->id, 403);
        $demo = config('stockguard.demo_mode');
        $rules = [
            'product_id' => 'required|exists:products,id',
            'evidence' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
        if ($demo) {
            $rules['detected_qty'] = 'required|integer|min:0';
            $rules['confidence'] = 'required|numeric|min:0|max:1';
        }
        $data = $request->validate($rules);
        $productId = (int) $data['product_id'];
        DB::transaction(function () use ($transaction, $productId) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->status !== 'scanned' || $transaction->scanEvents()->count() !== (int) $transaction->items()->count()) {
                throw ValidationException::withMessages(['evidence' => 'Pindai semua SKU sebelum verifikasi kamera.']);
            }
            if (!$transaction->items()->where('product_id', $productId)->exists() || $transaction->visionEvents()->where('product_id', $productId)->exists()) {
                throw ValidationException::withMessages(['product_id' => 'Produk tidak valid atau sudah diverifikasi.']);
            }
            $transaction->update(['status' => 'verifying']);
        });
        $path = null;
        try {
            $path = $request->file('evidence')->store("evidence/{$transaction->id}", 'local');
            if (!$path) throw new \RuntimeException('Foto gagal disimpan.');
            if ($demo) {
                $workflow->recordVision($transaction->id, $productId, (int) $data['detected_qty'], (float) $data['confidence'], $path, [], 'demo', $request->user()->id);
            } else {
                if (!config('stockguard.yolo_service_url')) {
                    throw ValidationException::withMessages(['evidence' => 'YOLO_SERVICE_URL belum dikonfigurasi.']);
                }
                ProcessVisionCapture::dispatch($transaction->id, $productId, $path, $request->user()->id);
            }
        } catch (\Throwable $exception) {
            Transaction::whereKey($transaction->id)->where('status', 'verifying')->update(['status' => 'scanned']);
            if ($path) Storage::disk('local')->delete($path);
            throw $exception;
        }
        return redirect()->route('transactions.show', $transaction)->with('success', $demo ? 'Hasil simulasi disimpan.' : 'Foto dikirim untuk analisis YOLO. Muat ulang untuk melihat hasil.');
    }

    public function evidence(Request $request, VisionEvent $visionEvent)
    {
        $transaction = $visionEvent->transaction;
        abort_if($request->user()->role === 'staff' && (int) $transaction->user_id !== (int) $request->user()->id, 403);
        abort_unless($visionEvent->evidence_path && Storage::disk('local')->exists($visionEvent->evidence_path), 404);
        return Storage::disk('local')->response($visionEvent->evidence_path);
    }
}
