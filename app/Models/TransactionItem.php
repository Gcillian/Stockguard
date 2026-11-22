<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class TransactionItem extends Model {public $timestamps=false; protected $fillable=['transaction_id','product_id','quantity']; protected $casts=['quantity'=>'integer']; public function product(){return $this->belongsTo(Product::class);}}
