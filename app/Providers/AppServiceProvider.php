<?php

namespace App\Providers;

use App\Models\CpCase;
use App\Models\Produk;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.custom');

        // Badge notifikasi "produk baru" (auto-created pas import order
        // harian, belum di-review) — dipasang di layout utama biar
        // kelihatan di semua halaman, admin doang yang urus Master Produk.
        View::composer('layouts.app', function ($view) {
            $view->with('produkBaruCount', Auth::check() && Auth::user()->isAdmin()
                ? Produk::pendingReview()->count()
                : 0);

            // Badge notifikasi "pengajuan takedown menunggu approval" — cuma
            // Head of SRN yang boleh approve/reject, jadi cuma dia yang perlu
            // lihat badge-nya (Admin sengaja gak ikut, approval-nya harus
            // jelas tanggung jawab Head).
            $view->with('takedownApprovalCount', Auth::check() && Auth::user()->isHead()
                ? CpCase::where('status_takedown', 'Menunggu Approval')->count()
                : 0);
        });
    }
}
