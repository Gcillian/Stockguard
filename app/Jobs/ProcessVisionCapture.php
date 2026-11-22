<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\WithdrawalWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProcessVisionCapture implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(public int $transactionId, public int $productId, public string $path, public int $userId) {}

    public function handle(WithdrawalWorkflow $workflow): void
    {
        $transaction = Transaction::with('items.product')->findOrFail($this->transactionId);
        $item = $transaction->items->firstWhere('product_id', $this->productId);
        if (!$item || $transaction->status !== 'verifying') return;

        $url = rtrim(config('stockguard.yolo_service_url'), '/') . '/detect';
        $request = Http::timeout(45);
        if (config('stockguard.yolo_service_token')) $request = $request->withHeaders(['X-Service-Token' => config('stockguard.yolo_service_token')]);
        $response = $request
            ->attach('image', Storage::disk('local')->get($this->path), basename($this->path))
            ->post($url, ['sku' => $item->product->sku, 'expected_qty' => $item->quantity]);
        $response->throw();
        $data = $response->json();
        if (!is_array($data) || !isset($data['detected_qty'], $data['confidence']) || !is_int($data['detected_qty']) || $data['detected_qty'] < 0 || !is_numeric($data['confidence']) || $data['confidence'] < 0 || $data['confidence'] > 1 || ($data['sku'] ?? null) !== $item->product->sku || ($data['expected_qty'] ?? null) !== $item->quantity || !is_array($data['bounding_boxes'] ?? [])) {
            throw new \UnexpectedValueException('Respons YOLO tidak valid.');
        }
        $workflow->recordVision($this->transactionId, $this->productId, (int) $data['detected_qty'], (float) $data['confidence'], $this->path, $data['bounding_boxes'] ?? [], 'yolo', $this->userId);
    }

    public function failed(\Throwable $exception): void
    {
        Transaction::whereKey($this->transactionId)->where('status', 'verifying')->update(['status' => 'scanned']);
        Storage::disk('local')->delete($this->path);
        app(WithdrawalWorkflow::class)->audit(Transaction::find($this->transactionId), $this->userId, 'vision_failed', ['product_id' => $this->productId, 'reason' => $exception->getMessage()]);
    }
}
