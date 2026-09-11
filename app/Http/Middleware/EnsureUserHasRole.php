<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Anda tidak punya akses ke halaman ini.');
        }

        // Head of SRN setara Admin penuh — lolos di setiap gerbang role:admin
        // tanpa perlu didaftarkan satu-satu di tiap route.
        $isAdminEquivalent = $user->isHead() && in_array('admin', $roles, true);

        if (! $isAdminEquivalent && ! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak punya akses ke halaman ini.');
        }

        return $next($request);
    }
}
