<?php

namespace App\Services;

use App\Models\Anomaly;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\VisionEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalWorkflow
{
    public function audit(?Transaction $transaction, int $userId, string $action, array $details = []): void
    {
        AuditLog::create([
            'transaction_id' => $transaction?->id,
            'user_id' => $userId,
            'action' => $action,
            'details' => $details ?: null,
        ]);
    }

    public function flagScanMismatch(Transaction $transaction, int $userId, string $description, array $details = []): void
    {
        $this->flag($transaction, 'scan_mismatch', $description, $userId);
        $this->audit($transaction, $userId, 'scan_mismatch', $details);
    }

    public function recordVision(int $transactionId, int $productId, int $detected, float $confidence, string $path, array $boxes, string $source, int $userId): string
    {
        return DB::transaction(function () use ($transactionId, $productId, $detected, $confidence, $path, $boxes, $source, $userId) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transactionId);
            if ($transaction->status !== 'verifying') {
                throw ValidationException::withMessages(['evidence' => 'Transaksi tidak sedang diverifikasi.']);
            }

            $item = $transaction->items()->where('product_id', $productId)->firstOrFail();
            if ($transaction->visionEvents()->where('product_id', $productId)->exists()) {
                throw ValidationException::withMessages(['evidence' => 'Produk ini sudah diverifikasi.']);
            }

            VisionEvent::create([
                'transaction_id' => $transactionId,
                'product_id' => $productId,
                'detected_qty' => $detected,
                'confidence' => $confidence,
                'evidence_path' => $path,
                'bounding_boxes' => $boxes,
                'source' => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->audit($transaction, $userId, 'vision_result', compact('productId', 'detected', 'confidence', 'source'));

            $threshold = config('stockguard.confidence_threshold', .8);
            if ($detected !== $item->quantity || $confidence < $threshold) {
                $type = $detected !== $item->quantity ? 'quantity_mismatch' : 'low_confidence';
                $this->flag($transaction, $type, "Produk {$item->product->sku}: diminta {$item->quantity}, terdeteksi {$detected}, confidence {$confidence}.", $userId);
            }

            if ($transaction->visionEvents()->count() === $transaction->items()->count()) {
                if ($transaction->anomaly()->where('status', 'pending')->exists()) {
                    $transaction->update(['status' => 'review']);
                    return 'review';
                }
                return $this->complete($transaction, $userId) ? 'completed' : 'review';
            }

            $transaction->update(['status' => 'scanned']);
            return 'scanned';
        });
    }

    public function recordDemoCheckpoint(int $transactionId, int $detectedTotal, float $confidence, string $path, int $userId): string
    {
        return DB::transaction(function () use ($transactionId, $detectedTotal, $confidence, $path, $userId) {
            $transaction = Transaction::lockForUpdate()->with('items.product')->findOrFail($transactionId);
            if ($transaction->status !== 'verifying') {
                throw ValidationException::withMessages(['demo' => 'Transaksi tidak sedang diverifikasi.']);
            }

            $expectedTotal = (int) $transaction->items->sum('quantity');
            foreach ($transaction->items as $item) {
                VisionEvent::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $item->product_id,
                    'detected_qty' => $item->quantity,
                    'confidence' => $confidence,
                    'evidence_path' => $path,
                    'bounding_boxes' => [],
                    'source' => 'demo_static_4box',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->audit($transaction, $userId, 'vision_result', ['detected_total' => $detectedTotal, 'expected_total' => $expectedTotal, 'confidence' => $confidence, 'source' => 'demo_static_4box']);

            if ($detectedTotal !== $expectedTotal) {
                $this->flag($transaction, 'quantity_mismatch', "Total box diminta {$expectedTotal}, terdeteksi {$detectedTotal}, confidence {$confidence}.", $userId);
                $transaction->update(['status' => 'review']);
                return 'review';
            }

            return $this->complete($transaction, $userId) ? 'completed' : 'review';
        });
    }

    public function approve(Anomaly $anomaly, int $userId, ?string $note): void
    {
        DB::transaction(function () use ($anomaly, $userId, $note) {
            $transaction = Transaction::lockForUpdate()->findOrFail($anomaly->transaction_id);
            $anomaly = Anomaly::lockForUpdate()->findOrFail($anomaly->id);
            if ($transaction->status !== 'review' || $anomaly->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Anomali ini sudah diproses.']);
            }
            if ($transaction->visionEvents()->count() !== $transaction->items()->count()) {
                throw ValidationException::withMessages(['decision' => 'Semua produk harus diverifikasi sebelum persetujuan.']);
            }
            if (!$this->deductStock($transaction)) {
                throw ValidationException::withMessages(['decision' => 'Stok tidak cukup. Perbarui stok sebelum menyetujui.']);
            }
            $anomaly->update(['status' => 'approved', 'reviewer_id' => $userId, 'review_note' => $note, 'reviewed_at' => now()]);
            $transaction->update(['status' => 'completed']);
            $this->audit($transaction, $userId, 'review_approved', ['note' => $note]);
        });
    }

    public function reject(Anomaly $anomaly, int $userId, string $note): void
    {
        DB::transaction(function () use ($anomaly, $userId, $note) {
            $transaction = Transaction::lockForUpdate()->findOrFail($anomaly->transaction_id);
            $anomaly = Anomaly::lockForUpdate()->findOrFail($anomaly->id);
            if ($transaction->status !== 'review' || $anomaly->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Anomali ini sudah diproses.']);
            }
            $anomaly->update(['status' => 'rejected', 'reviewer_id' => $userId, 'review_note' => $note, 'reviewed_at' => now()]);
            $transaction->update(['status' => 'rejected']);
            $this->audit($transaction, $userId, 'review_rejected', ['note' => $note]);
        });
    }

    private function complete(Transaction $transaction, int $userId): bool
    {
        if (!$this->deductStock($transaction)) {
            $this->flag($transaction, 'insufficient_stock', 'Stok berubah sebelum transaksi selesai.', $userId);
            $transaction->update(['status' => 'review']);
            return false;
        }
        $transaction->update(['status' => 'completed']);
        $this->audit($transaction, $userId, 'completed');
        return true;
    }

    private function deductStock(Transaction $transaction): bool
    {
        $items = $transaction->items()->orderBy('product_id')->get();
        $products = Product::whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($items as $item) {
            if ($products[$item->product_id]->stock < $item->quantity || StockMovement::where('transaction_id', $transaction->id)->where('product_id', $item->product_id)->exists()) {
                return false;
            }
        }
        foreach ($items as $item) {
            $products[$item->product_id]->decrement('stock', $item->quantity);
            StockMovement::create(['transaction_id' => $transaction->id, 'product_id' => $item->product_id, 'quantity' => -$item->quantity, 'created_at' => now()]);
        }
        return true;
    }

    private function flag(Transaction $transaction, string $type, string $description, int $userId): void
    {
        $anomaly = $transaction->anomaly()->where('status', 'pending')->first();
        if ($anomaly) $anomaly->update(['description' => $anomaly->description."\n".$description]);
        else Anomaly::create(['transaction_id' => $transaction->id, 'type' => $type, 'description' => $description, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->audit($transaction, $userId, 'pending_review', compact('type', 'description'));
    }
}
