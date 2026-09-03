<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finance only ever gets Poin Mitra, Penukaran Poin, and Pengajuan Buy Back
 * (view + approve only there — create/edit/delete on buyback is separately
 * gated per-route). Deny-by-default: every route not in the allowlist is
 * blocked, so a future new route doesn't accidentally leak through.
 */
class RestrictFinanceAccess
{
    private const ALLOWED_PREFIXES = ['poin.', 'poin-redemption.', 'buyback.'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'finance') {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';

        if ($routeName === 'logout') {
            return $next($request);
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return $next($request);
            }
        }

        abort(403, 'Akun Finance hanya bisa mengakses menu Poin Mitra, Penukaran Poin, dan Pengajuan Buy Back.');
    }
}
