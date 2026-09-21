<?php

namespace App\Http\Controllers;

use App\Models\LmsStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master LMS (grup menu Admin) — kelola daftar video/langkah LMS per
 * platform yang dipakai menu Set Up LMS. Menambah/menonaktifkan video
 * langsung mengubah % Selesai semua mitra (dihitung live dari video aktif).
 */
class MasterLmsController extends Controller
{
    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->input('tab'), LmsStep::PLATFORMS) ? $request->input('tab') : 'shopee';

        $steps = LmsStep::where('platform', $tab)
            ->withCount('completions')
            ->orderBy('urutan')->orderBy('id')
            ->get();

        return view('master-lms.index', [
            'tab' => $tab,
            'platforms' => LmsStep::PLATFORMS,
            'steps' => $steps,
            'nextUrutan' => ($steps->max('urutan') ?? 0) + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'in:'.implode(',', array_keys(LmsStep::PLATFORMS))],
            'judul' => ['required', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $urutan = $data['urutan'] ?? ((LmsStep::where('platform', $data['platform'])->max('urutan') ?? 0) + 1);

        LmsStep::create([
            'platform' => $data['platform'],
            'judul' => trim($data['judul']),
            'urutan' => $urutan,
            'aktif' => true,
        ]);

        return redirect()->route('master-lms.index', ['tab' => $data['platform']])
            ->with('status', 'Video "'.trim($data['judul']).'" ditambahkan ke LMS '.LmsStep::PLATFORMS[$data['platform']].'.');
    }

    public function update(Request $request, LmsStep $lmsStep): RedirectResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'urutan' => ['required', 'integer', 'min:1', 'max:999'],
            'aktif' => ['nullable', 'boolean'],
        ]);

        $lmsStep->update([
            'judul' => trim($data['judul']),
            'urutan' => $data['urutan'],
            'aktif' => $request->boolean('aktif'),
        ]);

        return redirect()->route('master-lms.index', ['tab' => $lmsStep->platform])
            ->with('status', 'Video "'.$lmsStep->judul.'" diperbarui.');
    }

    public function destroy(LmsStep $lmsStep): RedirectResponse
    {
        $jumlah = $lmsStep->completions()->count();

        if ($jumlah > 0) {
            return redirect()->route('master-lms.index', ['tab' => $lmsStep->platform])
                ->withErrors(['hapus' => 'Video "'.$lmsStep->judul.'" tidak bisa dihapus karena sudah dicentang '.$jumlah.' mitra (bukti GDrive-nya ikut hilang). Nonaktifkan saja kalau tidak dipakai lagi.']);
        }

        $lmsStep->delete();

        return redirect()->route('master-lms.index', ['tab' => $lmsStep->platform])
            ->with('status', 'Video "'.$lmsStep->judul.'" dihapus.');
    }
}
