<?php

namespace App\Http\Controllers;

use App\Services\DataHealthService;
use Illuminate\View\View;

class DataHealthController extends Controller
{
    public function index(): View
    {
        return view('data-health.index', [
            'checks' => DataHealthService::run(),
        ]);
    }
}
