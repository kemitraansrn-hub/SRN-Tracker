<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finance only ever gets Poin Mitra, Penukaran Poin, Pengajuan Buy Back,
 * Tracking Referral, Pengakuisisi, Tracking AR / Data Piutang, dan
 * Dashboard (view + approve only di buyback/poin-redemption, view-only di
 * ar/akuisisi-referral/pengakuisisi — create/edit/delete tetap digerbang
 * per-route lewat role:admin). Deny-by-default: every route not in the
 * allowlist is blocked, so a future new route doesn't accidentally leak
 * through.
 *
 * 2026-11-16: akuisisi-referral (Tracking Referral) & pengakuisisi
 * (Pengakuisisi) sudah lama ada di sidebar finance tapi kelewatan gak
 * pernah ditambahkan ke sini -- 403 ini bukan fitur baru, murni bug lama
 * yang baru ketahuan sekarang.
 */
class RestrictFinanceAccess
{
    private const ALLOWED_PREFIXES = ['poin.', 'poin-redemption.', 'buyback.', 'akuisisi-referral.', 'pengakuisisi.', 'ar.', 'dashboard'];

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

        abort(403, 'Akun Finance hanya bisa mengakses menu Poin Mitra, Penukaran Poin, Pengajuan Buy Back, Tracking Referral, Pengakuisisi, dan Tracking AR.');
    }
}
