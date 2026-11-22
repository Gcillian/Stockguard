<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_adjust_stock_and_search_by_transaction_id(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'role' => 'admin', 'password' => bcrypt('password')]);
        $warehouse = Warehouse::create(['name' => 'Gudang', 'location' => 'Jakarta']);
        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'Kotak merah', 'sku' => 'SG-001', 'barcode' => '8990000000001', 'stock' => 100,
        ])->assertRedirect();
        $product = Product::firstOrFail();
        $this->assertDatabaseHas('stock_adjustments', ['product_id' => $product->id, 'quantity' => 100, 'stock_after' => 100]);

        $this->put(route('products.update', $product), [
            'name' => 'Kotak merah', 'sku' => 'SG-001', 'barcode' => '8990000000001', 'stock' => 96, 'reason' => 'Stok opname',
        ])->assertRedirect();
        $this->assertSame(96, $product->fresh()->stock);
        $this->assertSame(-4, StockAdjustment::latest('id')->firstOrFail()->quantity);
        $this->get(route('products.index'))->assertOk()->assertSee('Kotak merah')->assertSee('quantity-badge negative', false);

        $transaction = Transaction::create(['user_id' => $admin->id, 'warehouse_id' => $warehouse->id, 'status' => 'completed']);
        StockMovement::create(['transaction_id' => $transaction->id, 'product_id' => $product->id, 'quantity' => -2, 'created_at' => now()->addSecond()]);
        $this->get(route('products.index', ['search' => 'OUT-'.$transaction->id]))->assertOk()->assertSee('Kotak merah');
        $this->get(route('products.index', ['search' => 'Tidak ditemukan']))->assertOk()->assertDontSee('Kotak merah');
    }

    public function test_non_admin_cannot_manage_products_or_stock(): void
    {
        $staff = User::create(['name' => 'Staff', 'email' => 'staff@example.test', 'role' => 'staff', 'password' => bcrypt('password')]);
        $product = Product::create(['name' => 'Kotak', 'sku' => 'BOX', 'barcode' => '1234', 'stock' => 10]);
        $this->actingAs($staff)->get(route('products.index'))->assertForbidden();
        $this->post(route('products.store'), ['name' => 'Lain', 'sku' => 'OTHER', 'barcode' => '9999', 'stock' => 5])->assertForbidden();
        $this->put(route('products.update', $product), ['name' => 'Kotak', 'sku' => 'BOX', 'barcode' => '1234', 'stock' => 0, 'reason' => 'Tes'])->assertForbidden();
        $this->assertSame(10, $product->fresh()->stock);
    }
}
