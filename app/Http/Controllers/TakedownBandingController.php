<?php

namespace App\Http\Controllers;

use App\Models\CpTakedownBanding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kasus yang statusnya udah "Take Down" beneran (bukan lagi cuma diajukan)
 * muncul di sini — satu baris CpTakedownBanding per kasus, otomatis dibikin
 * dari CpCaseController::markTakeDown(). Tombol "Banding" di sini yang
 * dipakai buat nyatet detail proses banding-nya (kalau mitra ngajuin).
 */
class TakedownBandingController extends Controller
{
    public const STATUS_OPTIONS = ['Pending', 'Approved', 'Rejected'];

    public const SP_OPTIONS = ['SP1', 'SP2', 'SP3'];

    public function index(Request $request): View
    {
        $query = CpTakedownBanding::with(['cpCase.mitra', 'cpCase.produk'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('cpCase', function ($qq) use ($request) {
                $qq->where('nama_toko', 'like', '%'.$request->input('q').'%')
                    ->orWhere('kode', 'like', '%'.$request->input('q').'%');
            }))
            ->latest('tanggal_takedown');

        return view('takedown-banding.index', [
            'rows' => $query->paginate(20)->withQueryString(),
            'statusOptions' => self::STATUS_OPTIONS,
            'spOptions' => self::SP_OPTIONS,
        ]);
    }

    public function update(Request $request, CpTakedownBanding $cpTakedownBanding): RedirectResponse
    {
        $data = $request->validate([
            'alasan_takedown' => ['nullable', 'string'],
            'tanggal_banding' => ['nullable', 'date'],
            'status_banding' => ['nullable', 'string', 'in:'.implode(',', self::STATUS_OPTIONS)],
            'sp' => ['nullable', 'string', 'in:'.implode(',', self::SP_OPTIONS)],
            'keputusan_final' => ['nullable', 'string'],
            'status_takedown_final' => ['nullable', 'string', 'in:'.implode(',', self::STATUS_OPTIONS)],
        ]);

        $cpTakedownBanding->update($data);

        return redirect()->route('takedown-banding.index')->with('status', 'Data banding berhasil disimpan.');
    }
}
