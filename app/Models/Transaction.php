<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model {
    protected $guarded = [];
    protected $casts = ['is_demo' => 'boolean'];
    public function items() { return $this->hasMany(TransactionItem::class); }
    public function user() { return $this->belongsTo(User::class); }
}
