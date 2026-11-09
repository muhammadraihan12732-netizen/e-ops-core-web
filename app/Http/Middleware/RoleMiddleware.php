<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware {
    public function handle(Request $request, Closure $next, ...$roles) {
        $role = $request->user()?->role?->name;
        if (!in_array($role, $roles)) {
            return response()->json(['message' => 'Anda tidak punya akses'], 403);
        }
        return $next($request);
    }
}
