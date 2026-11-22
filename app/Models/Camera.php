<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Camera extends Model {public $timestamps=false; protected $fillable=['warehouse_id','name','status']; public function warehouse(){return $this->belongsTo(Warehouse::class);}}
