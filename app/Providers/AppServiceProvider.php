<?php

namespace App\Providers;

use App\Models\CpCase;
use App\Models\PriceAdjustmentRequest;
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
            $view->with('produkBaruCount', Auth::check() && Auth::user()->canAccessAdminGroup()
                ? Produk::pendingReview()->count()
                : 0);

            // Badge notifikasi "pengajuan takedown menunggu approval" — Head
            // of SRN, Manager (setara Head), dan Supervisor (bukan bagian
            // grup Admin yang dikecualikan) yang boleh approve/reject (Admin
            // sengaja gak ikut, approval-nya harus jelas tanggung jawab
            // Head/setaranya, bukan sekadar wewenang admin).
            $view->with('takedownApprovalCount', Auth::check() && Auth::user()->canActAsHead()
                ? CpCase::where('status_takedown', 'Menunggu Approval')->count()
                : 0);

            // Badge notifikasi buat Compliance: kasus yang keputusan Head-nya
            // udah keluar (Approved atau Rejected) — Compliance perlu tindak
            // lanjut (List to Shopee kalau Approved, atau ajukan ulang kalau
            // Rejected).
            $view->with('takedownKeputusanCount', Auth::check() && Auth::user()->isCompliance()
                ? CpCase::whereIn('status_takedown', ['Approved', 'Rejected'])->count()
                : 0);

            // Badge notifikasi "pengajuan Price Adjustment menunggu approval"
            // — dikunci ketat cuma buat Head of SRN & Manager (bukan
            // canActAsHead(), Supervisor sengaja gak ikut buat approval ini).
            $view->with('priceAdjustmentApprovalCount', Auth::check() && Auth::user()->isHeadOrManager()
                ? PriceAdjustmentRequest::where('status_approval', 'Pending')->count()
                : 0);

            // Badge notifikasi buat Compliance: hasil keputusan Price
            // Adjustment (Approved atau Rejected) yang BELUM dibuka — biar
            // Compliance tau mitra mana yang lagi punya izin sah turun harga
            // (jangan ditandai pelanggaran di Tracking CP) atau yang ditolak.
            // Ke-mark "dilihat" begitu Compliance buka halaman Price
            // Adjustment Monitoring (lihat PriceAdjustmentRequestController::
            // monitoring()), jadi badge-nya beneran ilang pas dibuka, bukan
            // sekadar total mentah yang nyangkut terus.
            $view->with('priceAdjustmentKeputusanCount', Auth::check() && Auth::user()->isCompliance()
                ? PriceAdjustmentRequest::whereIn('status_approval', ['Approved', 'Rejected'])->whereNull('dilihat_compliance_at')->count()
                : 0);
        });
    }
}
