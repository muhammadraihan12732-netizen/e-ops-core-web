<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SystemMetric extends Model {
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['db_up' => 'boolean', 'created_at' => 'datetime'];
}
