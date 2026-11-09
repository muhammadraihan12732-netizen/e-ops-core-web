<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog, Role, User};
use Illuminate\Http\Request;

class UserController extends Controller {
    public function index(Request $r) {
        return User::with('role')
            ->when($r->search, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($r->role_id, fn ($q, $id) => $q->where('role_id', $id))
            ->orderBy('name')
            ->paginate(min((int) $r->get('per_page', 10), 100));
    }

    public function roles() { return Role::orderBy('id')->get(); }

    public function store(Request $r) {
        $d = $r->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:100',
            'role_id' => 'required|exists:roles,id',
        ]);
        $u = User::create($d + ['is_active' => true]);
        ActivityLog::record('user.create', $u->email);
        return response()->json($u->load('role'), 201);
    }

    public function update(Request $r, User $user) {
        $d = $r->validate([
            'name' => 'required|string|max:255',
            'email' => "required|email|max:255|unique:users,email,{$user->id}",
            'role_id' => 'required|exists:roles,id',
            'password' => 'nullable|string|min:8|max:100',
            'is_active' => 'boolean',
        ]);
        if (empty($d['password'])) unset($d['password']); // kosong = password tidak diubah

        $self = $user->id === $r->user()->id;
        $roleChanged = (int) $d['role_id'] !== (int) $user->role_id;
        $deactivating = array_key_exists('is_active', $d) && !$d['is_active'];
        // admin tidak boleh mengunci dirinya sendiri dari sistem
        abort_if($self && ($roleChanged || $deactivating), 422, 'Anda tidak dapat mengubah role atau menonaktifkan akun Anda sendiri');

        $user->update($d);

        // akun dinonaktifkan atau password diganti -> token lama dicabut (kecuali sesi admin yang sedang dipakai)
        if ($deactivating || isset($d['password'])) {
            $user->tokens()
                ->when($self, fn ($q) => $q->where('id', '!=', $r->user()->currentAccessToken()->id))
                ->delete();
        }
        ActivityLog::record('user.update', $user->email . ($deactivating ? ' (dinonaktifkan)' : ''));
        return $user->load('role');
    }
    // Sengaja tidak ada destroy(): transactions.user_id wajib terisi, jadi akun dinonaktifkan, bukan dihapus.
}
