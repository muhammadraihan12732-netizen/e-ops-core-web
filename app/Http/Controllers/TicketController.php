<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog, Ticket, TicketReply};
use Illuminate\Http\Request;

class TicketController extends Controller {
    private function staff(Request $r) { return in_array($r->user()->role->name, ['admin', 'support']); }

    public function index(Request $r) {
        $q = Ticket::with(['user:id,name', 'replies.user:id,name'])->latest()
            ->when($r->status, fn ($q, $s) => $q->where('status', $s));
        if (!$this->staff($r)) $q->where('user_id', $r->user()->id);
        return $q->limit(200)->get();
    }
    public function store(Request $r) {
        $d = $r->validate([
            'title' => 'required|string|max:255', 'description' => 'required|string|max:5000',
            'category' => 'required|in:Aplikasi,Jaringan,Perangkat,Akun', 'priority' => 'required|in:low,medium,high',
        ]);
        $t = Ticket::create($d + ['user_id' => $r->user()->id]);
        ActivityLog::record('ticket.create', $t->title);
        return response()->json($t, 201);
    }
    public function reply(Request $r, Ticket $ticket) {
        abort_unless($this->staff($r) || $ticket->user_id === $r->user()->id, 403);
        abort_if($ticket->status === 'closed', 422, 'Tiket sudah ditutup, tidak bisa dibalas');
        $d = $r->validate(['message' => 'required|string|max:5000']);
        $reply = TicketReply::create($d + ['ticket_id' => $ticket->id, 'user_id' => $r->user()->id]);
        ActivityLog::record('ticket.reply', "#{$ticket->id}");
        return response()->json($reply, 201);
    }
    public function status(Request $r, Ticket $ticket) {
        $d = $r->validate(['status' => 'required|in:open,in_progress,resolved,closed']);
        $ticket->update($d);
        ActivityLog::record('ticket.status', "#{$ticket->id} -> {$d['status']}");
        return $ticket;
    }
}
