<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WithdrawalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $supervisor;
    private Product $product;
    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->staff = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'role' => 'staff', 'password' => bcrypt('password')]);
        $this->supervisor = User::create(['name' => 'Supervisor', 'email' => 'supervisor@example.test', 'role' => 'supervisor', 'password' => bcrypt('password')]);
        $this->warehouse = Warehouse::create(['name' => 'Gudang', 'location' => 'Jakarta']);
        $this->product = Product::create(['sku' => 'BOX-1', 'barcode' => '123456789', 'name' => 'Box', 'stock' => 10]);
    }

    private function requestAndScan(int $quantity = 1): Transaction
    {
        $this->actingAs($this->staff)->post(route('transactions.store'), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]],
        ])->assertRedirect();
        $transaction = Transaction::firstOrFail();
        $this->assertSame('submitted', $transaction->status);
        $this->post(route('transactions.scan', $transaction), ['code' => $this->product->barcode])->assertRedirect();
        $this->assertSame('scanned', $transaction->fresh()->status);
        return $transaction;
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('box.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/RZkAAAAASUVORK5CYII='));
    }

    public function test_matching_capture_completes_once_and_deducts_stock(): void
    {
        $transaction = $this->requestAndScan();
        $this->actingAs($this->supervisor)->post(route('transactions.capture', $transaction), ['product_id' => $this->product->id, 'evidence' => $this->photo(), 'detected_qty' => 1, 'confidence' => .95])->assertRedirect();
        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->actingAs($this->supervisor)->post(route('transactions.capture', $transaction), ['product_id' => $this->product->id, 'evidence' => $this->photo(), 'detected_qty' => 1, 'confidence' => .95])->assertSessionHasErrors();
        $this->assertSame(9, $this->product->fresh()->stock);
    }

    public function test_mismatch_requires_supervisor_and_approval_deducts_stock(): void
    {
        $transaction = $this->requestAndScan();
        $this->actingAs($this->supervisor)->post(route('transactions.capture', $transaction), ['product_id' => $this->product->id, 'evidence' => $this->photo(), 'detected_qty' => 3, 'confidence' => .95])->assertRedirect();
        $this->assertSame('review', $transaction->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
        $anomaly = $transaction->anomaly;
        $this->actingAs($this->supervisor)->get(route('transactions.show', $transaction))->assertOk()->assertSee('Accept')->assertSee('Tidak sesuai');
        $this->actingAs($this->staff)->patch(route('reviews.update', $anomaly), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($this->supervisor)->patch(route('reviews.update', $anomaly), ['decision' => 'approve', 'note' => 'Bukti sudah diperiksa'])->assertRedirect();
        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock);
        $this->assertDatabaseHas('anomalies', ['id' => $anomaly->id, 'reviewer_id' => $this->supervisor->id, 'status' => 'approved']);
        $this->patch(route('reviews.update', $anomaly), ['decision' => 'approve'])->assertSessionHasErrors();
        $this->assertSame(9, $this->product->fresh()->stock);
    }

    public function test_wrong_scan_and_capture_before_scan_are_rejected(): void
    {
        $this->actingAs($this->staff)->post(route('transactions.store'), ['warehouse_id' => $this->warehouse->id, 'items' => [['product_id' => $this->product->id, 'quantity' => 1]]])->assertRedirect();
        $transaction = Transaction::firstOrFail();
        $this->post(route('transactions.scan', $transaction), ['code' => 'WRONG'])->assertSessionHasErrors('code');
        $this->assertDatabaseHas('anomalies', ['transaction_id' => $transaction->id, 'type' => 'scan_mismatch', 'status' => 'pending']);
        $this->actingAs($this->supervisor)->post(route('transactions.capture', $transaction), ['product_id' => $this->product->id, 'evidence' => $this->photo(), 'detected_qty' => 2, 'confidence' => .95])->assertSessionHasErrors('evidence');
        $this->assertSame('review', $transaction->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_rejection_keeps_stock_and_staff_cannot_see_other_requests(): void
    {
        $transaction = $this->requestAndScan();
        $this->actingAs($this->supervisor)->post(route('transactions.capture', $transaction), ['product_id' => $this->product->id, 'evidence' => $this->photo(), 'detected_qty' => 1, 'confidence' => .7])->assertRedirect();
        $this->actingAs($this->supervisor)->patch(route('reviews.update', $transaction->anomaly), ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->patch(route('reviews.update', $transaction->anomaly), ['decision' => 'reject', 'note' => 'Jumlah pada foto tidak cocok'])->assertRedirect();
        $this->assertSame('rejected', $transaction->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
        $other = User::create(['name' => 'Lain', 'email' => 'other@example.test', 'role' => 'staff', 'password' => bcrypt('password')]);
        $this->actingAs($other)->get(route('transactions.show', $transaction))->assertForbidden();
        $this->get(route('evidence.show', $transaction->visionEvents()->firstOrFail()))->assertForbidden();
    }

    public function test_multi_item_request_waits_for_every_capture(): void
    {
        $second = Product::create(['sku' => 'BOX-2', 'barcode' => '987654321', 'name' => 'Box kedua', 'stock' => 5]);
        $this->actingAs($this->staff)->post(route('transactions.store'), ['warehouse_id' => $this->warehouse->id, 'items' => [['product_id' => $this->product->id, 'quantity' => 1], ['product_id' => $second->id, 'quantity' => 1]]])->assertRedirect();
        $transaction = Transaction::firstOrFail();
        $this->post(route('transactions.scan', $transaction), ['code' => $this->product->sku])->assertRedirect();
        $this->assertSame('submitted', $transaction->fresh()->status);
        $this->post(route('transactions.scan', $transaction), ['code' => $second->barcode])->assertRedirect();
        $this->actingAs($this->supervisor)->post(route('transactions.capture', $transaction), ['product_id' => $this->product->id, 'evidence' => $this->photo(), 'detected_qty' => 2, 'confidence' => .95])->assertRedirect();
        $this->assertSame('scanned', $transaction->fresh()->status);
        $this->actingAs($this->supervisor)->post(route('transactions.capture', $transaction), ['product_id' => $second->id, 'evidence' => $this->photo(), 'detected_qty' => 1, 'confidence' => .95])->assertRedirect();
        $this->assertSame('review', $transaction->fresh()->status);
        $this->assertDatabaseCount('anomalies', 1);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_duplicate_box_code_warns_and_keeps_request_open(): void
    {
        $second = Product::create(['sku' => 'BOX-2', 'barcode' => '987654321', 'name' => 'Box kedua', 'stock' => 5]);
        $this->actingAs($this->staff)->post(route('transactions.store'), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1],
                ['product_id' => $second->id, 'quantity' => 1],
            ],
        ])->assertRedirect();
        $transaction = Transaction::firstOrFail();
        $this->post(route('transactions.scan', $transaction), ['code' => $this->product->barcode])->assertRedirect();
        $this->post(route('transactions.scan', $transaction), ['code' => $this->product->barcode])->assertSessionHas('warning');
        $this->assertSame('submitted', $transaction->fresh()->status);
        $this->assertDatabaseCount('anomalies', 0);
    }

    public function test_request_accepts_multiple_boxes_for_a_sku(): void
    {
        $this->actingAs($this->staff)->post(route('transactions.store'), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ])->assertRedirect();

        $this->assertDatabaseHas('transaction_items', ['quantity' => 3]);
    }

    public function test_one_sku_scan_covers_multiple_requested_boxes(): void
    {
        $this->actingAs($this->staff)->post(route('transactions.store'), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ])->assertRedirect();

        $transaction = Transaction::firstOrFail();
        $this->post(route('transactions.scan', $transaction), ['code' => $this->product->sku])->assertRedirect();

        $this->assertSame('scanned', $transaction->fresh()->status);
        $this->assertSame(1, $transaction->scanEvents()->count());
    }

    public function test_role_pages_render_and_staff_cannot_manage_master_data(): void
    {
        $this->actingAs($this->staff)->get(route('transactions.create'))->assertOk();
        $this->get(route('checkpoint.index'))->assertForbidden();
        $transaction = Transaction::create(['user_id' => $this->staff->id, 'warehouse_id' => $this->warehouse->id, 'status' => 'scanned']);
        $this->post(route('transactions.capture', $transaction), [])->assertForbidden();
        $this->get(route('products.index'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'role' => 'admin', 'password' => bcrypt('password')]);
        $this->actingAs($admin)->get(route('products.index'))->assertOk();
        $this->get(route('users.index'))->assertOk();
        $this->get(route('warehouses.index'))->assertOk();
        $this->get(route('audits.index'))->assertOk();
        $this->get(route('checkpoint.index'))->assertOk();
        $this->get(route('transactions.create'))->assertForbidden();
    }

    public function test_yolo_service_result_is_used_when_demo_mode_is_off(): void
    {
        config()->set('stockguard.demo_mode', false);
        config()->set('stockguard.yolo_service_url', 'http://vision.test');
        Http::fake(['vision.test/detect' => Http::response(['sku' => 'BOX-1', 'expected_qty' => 1, 'detected_qty' => 1, 'confidence' => .93, 'bounding_boxes' => []], 200)]);
        $transaction = $this->requestAndScan();
        $this->actingAs($this->supervisor)->post(route('transactions.capture', $transaction), ['product_id' => $this->product->id, 'evidence' => $this->photo()])->assertRedirect();
        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock);
        Http::assertSentCount(1);
    }

    public function test_staff_demo_checkpoint_uses_static_four_box_image_for_matching_request(): void
    {
        $extraProducts = collect(range(2, 4))->map(fn ($number) => Product::create(['sku' => 'BOX-'.$number, 'barcode' => '12345678'.$number, 'name' => 'Box '.$number, 'stock' => 10]));
        $requestedProducts = collect([$this->product])->merge($extraProducts);
        $this->actingAs($this->staff)->post(route('transactions.store'), [
            'warehouse_id' => $this->warehouse->id,
            'items' => $requestedProducts->map(fn ($product) => ['product_id' => $product->id, 'quantity' => 1])->values()->all(),
        ])->assertRedirect();
        $transaction = Transaction::firstOrFail();
        foreach ($requestedProducts as $product) {
            $this->post(route('transactions.scan', $transaction), ['code' => $product->barcode])->assertRedirect();
        }
        $this->assertSame('scanned', $transaction->fresh()->status);
        $this->get(route('transactions.show', $transaction))->assertOk()->assertSee('>Demonstrasi</button>', false);
        $this->actingAs($this->staff)->post(route('transactions.demo', $transaction))->assertRedirect();

        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertSame(9, $this->product->fresh()->stock);
        $vision = $transaction->visionEvents()->firstOrFail();
        $this->assertSame(1, $vision->detected_qty);
        $this->assertSame('demo_static_4box', $vision->source);
        Storage::disk('local')->assertExists($vision->evidence_path);
    }

    public function test_staff_demo_checkpoint_sends_non_four_request_to_review(): void
    {
        $second = Product::create(['sku' => 'BOX-2', 'barcode' => '987654321', 'name' => 'Box kedua', 'stock' => 5]);
        $this->actingAs($this->staff)->post(route('transactions.store'), [
            'warehouse_id' => $this->warehouse->id,
            'items' => [['product_id' => $this->product->id, 'quantity' => 1], ['product_id' => $second->id, 'quantity' => 1]],
        ])->assertRedirect();
        $transaction = Transaction::firstOrFail();
        $this->post(route('transactions.scan', $transaction), ['code' => $this->product->barcode])->assertRedirect();
        $this->post(route('transactions.scan', $transaction), ['code' => $second->barcode])->assertRedirect();
        $this->assertSame('scanned', $transaction->fresh()->status);
        $this->actingAs($this->staff)->post(route('transactions.demo', $transaction))->assertRedirect();

        $this->assertSame('review', $transaction->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseHas('anomalies', ['transaction_id' => $transaction->id, 'type' => 'quantity_mismatch', 'status' => 'pending']);
    }
}

