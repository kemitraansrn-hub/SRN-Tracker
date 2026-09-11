<?php

use App\Http\Controllers\ArController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BuybackRequestController;
use App\Http\Controllers\BuybackSettingController;
use App\Http\Controllers\CpCaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataDevelopmentController;
use App\Http\Controllers\DataHealthController;
use App\Http\Controllers\DevelopmentModuleController;
use App\Http\Controllers\FollowupLogController;
use App\Http\Controllers\ForecastController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MitraController;
use App\Http\Controllers\NpdProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PoinController;
use App\Http\Controllers\PoinRedemptionController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\RewardCatalogController;
use App\Http\Controllers\RunRateTargetController;
use App\Http\Controllers\SalesDraftController;
use App\Http\Controllers\SegmentasiController;
use App\Http\Controllers\SpecialDealController;
use App\Http\Controllers\TakedownBandingController;
use App\Http\Controllers\TierTargetController;
use App\Http\Controllers\TrendController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeekPeriodController;
use App\Http\Controllers\WeeklyPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth', \App\Http\Middleware\RestrictFinanceAccess::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/new-mitra/{mitra}/toggle', [DashboardController::class, 'toggleNewMitra'])
        ->middleware('role:admin')->name('dashboard.toggle-new-mitra');
    Route::get('/trend', [TrendController::class, 'index'])->name('trend.index');
    Route::prefix('action-plan')->name('action-plan.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ActionPlanController::class, 'index'])->name('index');
        Route::get('/file', [\App\Http\Controllers\ActionPlanController::class, 'show'])->name('show');
        Route::post('/', [\App\Http\Controllers\ActionPlanController::class, 'store'])->middleware('role:admin')->name('store');
        Route::delete('/{actionPlan}', [\App\Http\Controllers\ActionPlanController::class, 'destroy'])->middleware('role:admin')->name('destroy');
    });
    Route::get('/omset-bulanan', [\App\Http\Controllers\OmsetBulananController::class, 'index'])->name('omset-bulanan.index');
    Route::get('/sales-overview', [\App\Http\Controllers\SalesOverviewController::class, 'index'])->name('sales-overview.index');
    Route::post('/trend/dashboard-card', [TrendController::class, 'saveDashboardCard'])
        ->middleware('role:admin')->name('trend.saveDashboardCard');
    Route::get('/segmentasi/{segmen}', [SegmentasiController::class, 'show'])->name('segmentasi.show');
    Route::get('/weekly-plan', [WeeklyPlanController::class, 'index'])->name('weekly-plan.index');

    Route::prefix('forecast')->name('forecast.')->group(function () {
        Route::get('/', [ForecastController::class, 'index'])->name('index');
        Route::post('/', [ForecastController::class, 'store'])->name('store');
        Route::delete('/{forecastRo}', [ForecastController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('ar')->name('ar.')->group(function () {
        Route::get('/', [ArController::class, 'index'])->name('index');
        Route::get('/export', [ArController::class, 'export'])->name('export');
        Route::post('/', [ArController::class, 'store'])->middleware('role:admin')->name('store');
        Route::put('/{arReceivable}', [ArController::class, 'update'])->middleware('role:admin')->name('update');
        Route::post('/{arReceivable}/bayar', [ArController::class, 'pay'])->name('pay');
        Route::delete('/payment/{payment}', [ArController::class, 'deletePayment'])->name('payment.destroy');
        Route::delete('/{arReceivable}', [ArController::class, 'destroy'])->middleware('role:admin')->name('destroy');
    });

    Route::middleware('role:admin')->prefix('produk')->name('produk.')->group(function () {
        Route::get('/', [ProdukController::class, 'index'])->name('index');
        Route::post('/', [ProdukController::class, 'store'])->name('store');
        Route::put('/{produk}', [ProdukController::class, 'update'])->name('update');
        Route::delete('/{produk}', [ProdukController::class, 'destroy'])->name('destroy');
        Route::get('/notifikasi', [ProdukController::class, 'notifications'])->name('notifications');
        Route::post('/notifikasi/{produk}/baca', [ProdukController::class, 'markReviewed'])->name('mark-reviewed');
        Route::post('/notifikasi/baca-semua', [ProdukController::class, 'markAllReviewed'])->name('mark-all-reviewed');
    });

    Route::prefix('buyback')->name('buyback.')->group(function () {
        Route::get('/', [BuybackRequestController::class, 'index'])->name('index');
        Route::middleware('role:admin,kae,head')->group(function () {
            Route::get('/create', [BuybackRequestController::class, 'create'])->name('create');
            Route::post('/', [BuybackRequestController::class, 'store'])->name('store');
            Route::get('/{buybackRequest}/edit', [BuybackRequestController::class, 'edit'])->name('edit');
            Route::put('/{buybackRequest}', [BuybackRequestController::class, 'update'])->name('update');
            Route::delete('/{buybackRequest}', [BuybackRequestController::class, 'destroy'])->name('destroy');
        });
        Route::post('/{buybackRequest}/approve', [BuybackRequestController::class, 'approve'])->middleware('role:admin,head,finance')->name('approve');
    });

    Route::get('/poin', [PoinController::class, 'index'])->name('poin.index');

    Route::middleware('role:admin')->prefix('reward')->name('reward.')->group(function () {
        Route::get('/', [RewardCatalogController::class, 'index'])->name('index');
        Route::post('/', [RewardCatalogController::class, 'store'])->name('store');
        Route::put('/{reward}', [RewardCatalogController::class, 'update'])->name('update');
        Route::delete('/{reward}', [RewardCatalogController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('penukaran-poin')->name('poin-redemption.')->group(function () {
        Route::get('/', [PoinRedemptionController::class, 'index'])->name('index');
        Route::get('/export', [PoinRedemptionController::class, 'export'])->name('export');
        Route::get('/create', [PoinRedemptionController::class, 'create'])->name('create');
        Route::post('/', [PoinRedemptionController::class, 'store'])->name('store');
        Route::get('/{poinRedemption}/edit', [PoinRedemptionController::class, 'edit'])->name('edit');
        Route::put('/{poinRedemption}', [PoinRedemptionController::class, 'update'])->name('update');
        Route::delete('/{poinRedemption}', [PoinRedemptionController::class, 'destroy'])->name('destroy');
        Route::post('/{poinRedemption}/approve', [PoinRedemptionController::class, 'approve'])->middleware('role:admin,head')->name('approve');
    });

    Route::prefix('input-penjualan')->name('sales-draft.')->group(function () {
        Route::get('/', [SalesDraftController::class, 'index'])->name('index');
        Route::get('/create', [SalesDraftController::class, 'create'])->name('create');
        Route::post('/', [SalesDraftController::class, 'store'])->name('store');
        Route::get('/{salesDraft}/edit', [SalesDraftController::class, 'edit'])->name('edit');
        Route::put('/{salesDraft}', [SalesDraftController::class, 'update'])->name('update');
        Route::delete('/{salesDraft}', [SalesDraftController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('data-development')->name('data-development.')->group(function () {
        Route::get('/', [DataDevelopmentController::class, 'index'])->name('index');
        Route::get('/download', [DataDevelopmentController::class, 'download'])->name('download');
        Route::post('/', [DataDevelopmentController::class, 'store'])->middleware('role:admin')->name('store');
    });

    Route::middleware('role:admin')->prefix('pengaturan/minggu')->name('pengaturan.')->group(function () {
        Route::get('/', [WeekPeriodController::class, 'edit'])->name('minggu');
        Route::post('/', [WeekPeriodController::class, 'update'])->name('minggu.update');
    });

    Route::middleware('role:admin')->prefix('pengaturan/target-run-rate')->name('run-rate-target.')->group(function () {
        Route::get('/', [RunRateTargetController::class, 'edit'])->name('edit');
        Route::post('/', [RunRateTargetController::class, 'update'])->name('update');
    });

    Route::middleware('role:admin')->prefix('pengaturan/tier-target')->name('tier-target.')->group(function () {
        Route::post('/{targetBulanan}', [TierTargetController::class, 'update'])->name('update');
    });

    Route::middleware('role:admin')->prefix('pengaturan/buyback')->name('buyback-setting.')->group(function () {
        Route::get('/', [BuybackSettingController::class, 'edit'])->name('edit');
        Route::post('/', [BuybackSettingController::class, 'update'])->name('update');
    });

    Route::middleware('role:admin')->prefix('pengaturan/backup')->name('backup.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/', [BackupController::class, 'run'])->name('run');
        Route::get('/{filename}/download', [BackupController::class, 'download'])->name('download');
    });

    Route::middleware('role:admin')->get('/data-health', [DataHealthController::class, 'index'])->name('data-health.index');

    Route::middleware('role:admin')->prefix('npd')->name('npd.')->group(function () {
        Route::get('/', [NpdProductController::class, 'index'])->name('index');
        Route::post('/{produk}/toggle', [NpdProductController::class, 'toggle'])->name('toggle');
    });

    Route::middleware('role:admin')->prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{targetUser}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{targetUser}', [UserController::class, 'update'])->name('update');
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

    Route::prefix('order')->name('order.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');

        Route::middleware('role:admin')->group(function () {
            Route::get('/{order}/edit', [OrderController::class, 'edit'])->name('edit');
            Route::put('/{order}', [OrderController::class, 'update'])->name('update');
        });
    });

    Route::prefix('followup')->name('followup.')->group(function () {
        Route::get('/', [FollowupLogController::class, 'index'])->name('index');
        Route::get('/export', [FollowupLogController::class, 'export'])->name('export');
        Route::get('/create', [FollowupLogController::class, 'create'])->name('create');
        Route::post('/', [FollowupLogController::class, 'store'])->name('store');
        Route::get('/{followupLog}/edit', [FollowupLogController::class, 'edit'])->name('edit');
        Route::put('/{followupLog}', [FollowupLogController::class, 'update'])->name('update');
        Route::delete('/{followupLog}', [FollowupLogController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('special-deal')->name('special-deal.')->group(function () {
        Route::get('/', [SpecialDealController::class, 'index'])->name('index');
        Route::get('/create', [SpecialDealController::class, 'create'])->name('create');
        Route::post('/', [SpecialDealController::class, 'store'])->name('store');
        Route::middleware('role:admin')->group(function () {
            Route::get('/template', [SpecialDealController::class, 'template'])->name('template');
            Route::get('/upload', [SpecialDealController::class, 'showUpload'])->name('upload.show');
            Route::post('/upload', [SpecialDealController::class, 'upload'])->name('upload');
        });
        Route::get('/{specialDeal}/edit', [SpecialDealController::class, 'edit'])->name('edit');
        Route::put('/{specialDeal}', [SpecialDealController::class, 'update'])->name('update');
        Route::delete('/{specialDeal}', [SpecialDealController::class, 'destroy'])->middleware('role:admin')->name('destroy');
        Route::get('/{specialDeal}/mou', [SpecialDealController::class, 'mou'])->name('mou');
    });

    Route::prefix('tracking-cp')->name('tracking-cp.')->group(function () {
        Route::get('/', [CpCaseController::class, 'index'])->name('index');
        Route::get('/create', [CpCaseController::class, 'create'])->name('create');
        Route::post('/', [CpCaseController::class, 'store'])->name('store');
        Route::get('/{cpCase}/edit', [CpCaseController::class, 'edit'])->name('edit');
        Route::put('/{cpCase}', [CpCaseController::class, 'update'])->name('update');
        Route::delete('/{cpCase}', [CpCaseController::class, 'destroy'])->name('destroy');
        Route::patch('/{cpCase}/follow-up/{round}', [CpCaseController::class, 'updateFollowUp'])->where('round', '1|2|3')->name('follow-up.update');
        Route::patch('/{cpCase}/case-close', [CpCaseController::class, 'updateCaseClose'])->name('case-close.update');
        Route::patch('/{cpCase}/status-kasus/progres', [CpCaseController::class, 'markProgres'])->name('status-kasus.progres');
        Route::patch('/{cpCase}/takedown', [CpCaseController::class, 'updateTakedown'])->name('takedown.update');
        Route::patch('/{cpCase}/takedown/decision', [CpCaseController::class, 'decideTakedown'])->name('takedown.decision');
        Route::patch('/{cpCase}/takedown/listed', [CpCaseController::class, 'markListedToShopee'])->name('takedown.listed');
        Route::patch('/{cpCase}/takedown/selesai', [CpCaseController::class, 'markTakeDown'])->name('takedown.selesai');
    });

    Route::prefix('take-down-banding')->name('takedown-banding.')->group(function () {
        Route::get('/', [TakedownBandingController::class, 'index'])->name('index');
        Route::patch('/{cpTakedownBanding}', [TakedownBandingController::class, 'update'])->name('update');
    });

    // Placeholder "Coming Soon" buat sisa menu Development — ganti satu-satu
    // dengan route/controller khusus begitu fiturnya beneran dibangun
    // (taruh route spesifiknya SEBELUM baris {page} ini biar gak ketiban
    // wildcard-nya).
    Route::get('development/{page}', [DevelopmentModuleController::class, 'show'])->name('development.show');
});
