<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Route (by name pattern) yang termasuk grup menu "Admin" di sidebar —
     * Supervisor sengaja dikecualikan dari daftar ini sesuai instruksi
     * "akses semua kecuali grup admin". Kalau ada fitur baru ditambahkan ke
     * grup Admin di sidebar, daftar ini (dan $adminRoutes di
     * layouts/app.blade.php) harus di-update bareng.
     */
    private const ADMIN_GROUP_ROUTE_PATTERNS = [
        'produk.*', 'reward.*', 'pengaturan.*', 'run-rate-target.*', 'tier-target.*',
        'buyback-setting.*', 'backup.*', 'data-health.*', 'npd.*', 'users.*', 'import.*',
    ];

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

        if (in_array('admin', $roles, true)) {
            // Head of SRN & Manager setara Admin penuh — lolos di setiap
            // gerbang role:admin tanpa perlu didaftarkan satu-satu di tiap
            // route.
            if ($user->isHead() || $user->isManager()) {
                return $next($request);
            }

            // Supervisor setara Admin KECUALI di route yang termasuk grup
            // menu Admin (Produk, Import, Users, Backup, dst).
            if ($user->isSupervisor() && ! $request->routeIs(...self::ADMIN_GROUP_ROUTE_PATTERNS)) {
                return $next($request);
            }
        }

        if (! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak punya akses ke halaman ini.');
        }

        return $next($request);
    }
}
