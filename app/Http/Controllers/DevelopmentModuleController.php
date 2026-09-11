<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Placeholder "Coming Soon" buat menu Development sampai masing-masing
 * fiturnya beneran dibangun (requirement/skema/halamannya sendiri-sendiri).
 * Begitu satu fitur mulai dibangun, ganti entry-nya di sini jadi route +
 * controller khusus (taruh route spesifiknya sebelum development/{page}
 * di routes/web.php biar gak ketiban wildcard ini).
 */
class DevelopmentModuleController extends Controller
{
    private const PAGES = [
        'price-adjustment-monitoring' => 'Price Adjustment Monitoring',
        'kpi-partnership-compliance' => 'KPI Partnership Compliance',
    ];

    public function show(string $page): View
    {
        if (! isset(self::PAGES[$page])) {
            throw new NotFoundHttpException();
        }

        return view('development.coming-soon', [
            'title' => self::PAGES[$page],
        ]);
    }
}
