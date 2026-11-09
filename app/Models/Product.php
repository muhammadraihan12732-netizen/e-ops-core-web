<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Product extends Model {
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
    public function category() { return $this->belongsTo(Category::class); }
}
