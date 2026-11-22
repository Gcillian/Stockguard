<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class Anomaly extends Model {public $timestamps=false; protected $fillable=['transaction_id','type','description','status','reviewer_id','review_note','reviewed_at']; protected $casts=['reviewed_at'=>'datetime']; public function transaction(){return $this->belongsTo(Transaction::class);} public function reviewer(){return $this->belongsTo(User::class,'reviewer_id');}}
