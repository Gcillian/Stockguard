<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class VisionEvent extends Model {public $timestamps=false; protected $fillable=['transaction_id','product_id','detected_qty','confidence','evidence_path','bounding_boxes','source','created_at','updated_at']; protected $casts=['detected_qty'=>'integer','confidence'=>'float','bounding_boxes'=>'array','created_at'=>'datetime']; public function transaction(){return $this->belongsTo(Transaction::class);} public function product(){return $this->belongsTo(Product::class);}}
