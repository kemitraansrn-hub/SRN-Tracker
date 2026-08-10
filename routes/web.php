<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FollowupLogController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MitraController;
use App\Http\Controllers\SegmentasiController;
use App\Http\Controllers\TrendController;
use App\Http\Controllers\WeekPeriodController;
use App\Http\Controllers\WeeklyPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/trend', [TrendController::class, 'index'])->name('trend.index');
    Route::post('/trend/dashboard-card', [TrendController::class, 'saveDashboardCard'])
        ->middleware('role:admin')->name('trend.saveDashboardCard');
    Route::get('/segmentasi/{segmen}', [SegmentasiController::class, 'show'])->name('segmentasi.show');
    Route::get('/weekly-plan', [WeeklyPlanController::class, 'index'])->name('weekly-plan.index');

    Route::middleware('role:admin')->prefix('pengaturan/minggu')->name('pengaturan.')->group(function () {
        Route::get('/', [WeekPeriodController::class, 'edit'])->name('minggu');
        Route::post('/', [WeekPeriodController::class, 'update'])->name('minggu.update');
    });

    Route::middleware('role:admin')->prefix('import')->name('import.')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::post('/', [ImportController::class, 'store'])->name('store');
        Route::get('/template/{jenis}', [ImportController::class, 'downloadTemplate'])->name('template');
    });

    Route::prefix('mitra')->name('mitra.')->group(function () {
        Route::get('/', [MitraController::class, 'index'])->name('index');

        Route::middleware('role:admin')->group(function () {
            Route::get('/create', [MitraController::class, 'create'])->name('create');
            Route::post('/', [MitraController::class, 'store'])->name('store');
            Route::get('/{mitra}/edit', [MitraController::class, 'edit'])->name('edit');
            Route::put('/{mitra}', [MitraController::class, 'update'])->name('update');
        });

        Route::get('/{mitra}', [MitraController::class, 'show'])->name('show');
    });

    Route::prefix('followup')->name('followup.')->group(function () {
        Route::get('/', [FollowupLogController::class, 'index'])->name('index');
        Route::get('/export', [FollowupLogController::class, 'export'])->name('export');
        Route::get('/create', [FollowupLogController::class, 'create'])->name('create');
        Route::post('/', [FollowupLogController::class, 'store'])->name('store');
    });
});
