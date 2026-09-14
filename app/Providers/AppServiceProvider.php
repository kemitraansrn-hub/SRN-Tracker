<?php

namespace App\Providers;

use App\Services\NotificationCenter;
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

        // Notifikasi terpadu — 1 lonceng di sidebar-foot buat semua kategori
        // (produk baru, takedown approval/keputusan, price adjustment
        // approval/keputusan). Lihat App\Services\NotificationCenter buat
        // detail per kategori & gimana status "unread"-nya dihitung per user.
        View::composer('layouts.app', function ($view) {
            $notifikasiList = Auth::check() ? NotificationCenter::forUser(Auth::user()) : [];

            $view->with('notifikasiList', $notifikasiList);
            $view->with('notifikasiUnreadCount', count($notifikasiList));
        });
    }
}
