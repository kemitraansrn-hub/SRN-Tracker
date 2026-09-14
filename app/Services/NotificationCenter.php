<?php

namespace App\Services;

use App\Models\CpCase;
use App\Models\NotificationDismissal;
use App\Models\PriceAdjustmentRequest;
use App\Models\Produk;
use App\Models\User;
use Carbon\Carbon;

/**
 * Notifikasi terpadu — 1 lonceng buat semua kategori notif (sebelumnya 5
 * badge terpisah di sidebar-foot). Tiap kategori: hitung jumlah live
 * (query biasa, gak difilter status baca) + tandai "unread" per USER kalau
 * item terbaru di kategori itu lebih baru dari terakhir kali user
 * bersangkutan buka halaman terkait (lihat dismiss()).
 *
 * "Sudah dibaca" itu per user masing-masing (bukan global kayak
 * reviewed_at Produk yang emang aksi bisnis beneran, atau field
 * dilihat_compliance_at lama yang udah dibuang) — 2 admin bisa punya
 * status baca notifikasi yang beda-beda buat kategori yang sama.
 */
class NotificationCenter
{
    /**
     * @return array<int, array{kategori: string, label: string, count: int, href: string, unread: bool}>
     */
    public static function forUser(User $user): array
    {
        $dismissals = NotificationDismissal::where('user_id', $user->id)->pluck('dismissed_at', 'kategori');
        $items = [];

        if ($user->canAccessAdminGroup()) {
            $items[] = self::buildItem(
                'produk_baru',
                'Produk baru menunggu review',
                Produk::pendingReview()->count(),
                Produk::pendingReview()->max('created_at'),
                $dismissals,
                route('produk.notifications')
            );
        }

        if ($user->canActAsHead()) {
            $query = CpCase::where('status_takedown', 'Menunggu Approval');
            $items[] = self::buildItem(
                'takedown_approval',
                'Pengajuan takedown menunggu approval',
                (clone $query)->count(),
                (clone $query)->max('updated_at'),
                $dismissals,
                route('tracking-cp.index', ['menunggu_approval' => 1])
            );
        }

        if ($user->isCompliance()) {
            $query = CpCase::whereIn('status_takedown', ['Approved', 'Rejected']);
            $items[] = self::buildItem(
                'takedown_keputusan',
                'Kasus yang keputusan Head-nya udah keluar',
                (clone $query)->count(),
                (clone $query)->max('updated_at'),
                $dismissals,
                route('tracking-cp.index', ['keputusan_head' => 1])
            );
        }

        if ($user->isHeadOrManager()) {
            $query = PriceAdjustmentRequest::where('status_approval', 'Pending');
            $items[] = self::buildItem(
                'price_adjustment_approval',
                'Pengajuan Price Adjustment menunggu approval',
                (clone $query)->count(),
                (clone $query)->max('created_at'),
                $dismissals,
                route('price-adjustment.index', ['status_approval' => 'Pending'])
            );
        }

        if ($user->isCompliance()) {
            $query = PriceAdjustmentRequest::whereIn('status_approval', ['Approved', 'Rejected']);
            $items[] = self::buildItem(
                'price_adjustment_keputusan',
                'Hasil keputusan Price Adjustment',
                (clone $query)->count(),
                (clone $query)->max('updated_at'),
                $dismissals,
                route('price-adjustment-monitoring.index', ['sudah_diputuskan' => 1])
            );
        }

        // Cuma kategori yang lagi unread yang ditampilin di dropdown — begitu
        // di-dismiss (baik lewat kunjungan halaman ataupun tombol "Hapus"),
        // item-nya ilang dari list sampai ada data baru lagi yang bikin
        // kategori itu unread lagi.
        return array_values(array_filter($items, fn ($item) => $item['unread']));
    }

    public static function unreadCount(User $user): int
    {
        // forUser() udah cuma balikin item yang unread, jadi tinggal hitung.
        return count(self::forUser($user));
    }

    /**
     * Tandai 1 kategori "sudah dibaca" buat user ini — dipanggil dari
     * controller halaman tujuan tiap kategori begitu halamannya kebuka
     * (bukan cuma dari klik dropdown notifikasi), jadi konsisten kapan pun
     * usernya ngeliat data itu.
     */
    public static function dismiss(User $user, string $kategori): void
    {
        NotificationDismissal::updateOrCreate(
            ['user_id' => $user->id, 'kategori' => $kategori],
            ['dismissed_at' => now()]
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Carbon>  $dismissals
     */
    private static function buildItem(string $kategori, string $label, int $count, ?string $latest, $dismissals, string $href): array
    {
        $dismissedAt = $dismissals->get($kategori);
        $unread = $latest !== null && (! $dismissedAt || Carbon::parse($latest)->gt($dismissedAt));

        return [
            'kategori' => $kategori,
            'label' => $label,
            'count' => $count,
            'href' => $href,
            'unread' => $unread,
        ];
    }
}
