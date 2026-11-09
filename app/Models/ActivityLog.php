<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model {
    protected $guarded = [];
    public function user() { return $this->belongsTo(User::class); }
    public static function record(string $action, ?string $desc = null) {
        static::create(['user_id' => auth()->id(), 'action' => $action, 'description' => $desc]);
    }
}
