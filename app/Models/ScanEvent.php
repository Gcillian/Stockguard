<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanEvent extends Model
{
    public $timestamps = false;
    protected $fillable = ['transaction_id', 'product_id', 'scanned_code', 'scanned_at'];
    protected $casts = ['scanned_at' => 'datetime'];
    public function product() { return $this->belongsTo(Product::class); }
}
