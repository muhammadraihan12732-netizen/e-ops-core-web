<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller {
    public function login(Request $r) {
        $r->validate(['email' => 'required|email', 'password' => 'required']);
        $u = User::with('role')->where('email', $r->email)->first();
        if (!$u || !Hash::check($r->password, $u->password)) {
            return response()->json(['message' => 'Email atau password salah'], 422);
        }
        if (!$u->is_active) {
            return response()->json(['message' => 'Akun dinonaktifkan. Hubungi admin.'], 403);
        }
        ActivityLog::create(['user_id' => $u->id, 'action' => 'login']);
        // token kedaluwarsa 12 jam (sebelumnya tidak pernah kedaluwarsa)
        return ['token' => $u->createToken('spa', ['*'], now()->addHours(12))->plainTextToken, 'user' => $u];
    }
    public function logout(Request $r) {
        ActivityLog::record('logout');
        $r->user()->currentAccessToken()->delete();
        return ['ok' => true];
    }
    public function me(Request $r) { return $r->user()->load('role'); }
}
