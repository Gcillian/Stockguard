<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    public $timestamps = false;
    protected $fillable = ['transaction_id', 'product_id', 'quantity', 'created_at'];
    protected $casts = ['created_at' => 'datetime', 'quantity' => 'integer'];
    public function transaction() { return $this->belongsTo(Transaction::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
