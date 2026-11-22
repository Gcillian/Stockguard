<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    public $timestamps = false;
    protected $fillable = ['product_id', 'user_id', 'quantity', 'stock_after', 'reason', 'created_at'];
    protected $casts = ['created_at' => 'datetime', 'quantity' => 'integer', 'stock_after' => 'integer'];

    public function product() { return $this->belongsTo(Product::class); }
    public function user() { return $this->belongsTo(User::class); }
}
