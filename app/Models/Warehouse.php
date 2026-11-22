<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Warehouse extends Model {protected $fillable=['name','location']; public function cameras(){return $this->hasMany(Camera::class);} public function transactions(){return $this->hasMany(Transaction::class);}}
