<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model {
    protected $guarded = [];
    public function replies() { return $this->hasMany(TicketReply::class); }
    public function user() { return $this->belongsTo(User::class); }
}
