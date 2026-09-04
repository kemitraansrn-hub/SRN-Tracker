<?php

namespace App\Http\Controllers;

use App\Models\ActionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * "Action Plan": satu file PDF slide yang aktif di satu waktu (bukan
 * daftar/riwayat) — upload baru otomatis gantiin yang lama. Semua user
 * login bisa lihat, cuma admin yang bisa upload/hapus.
 */
class ActionPlanController extends Controller
{
    public function index(): View
    {
        return view('action-plan.index', [
            'actionPlan' => ActionPlan::latest()->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'file.mimes' => 'File harus berupa PDF.',
            'file.max' => 'Ukuran file maksimal 20MB.',
        ]);

        $old = ActionPlan::latest()->first();

        $file = $request->file('file');
        $path = $file->store('action-plan');

        ActionPlan::create([
            'nama_file' => $file->getClientOriginalName(),
            'path' => $path,
            'ukuran_bytes' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        if ($old) {
            Storage::delete($old->path);
            $old->delete();
        }

        return redirect()->route('action-plan.index')->with('status', 'Action Plan berhasil diupload.');
    }

    public function show(): Response
    {
        $actionPlan = ActionPlan::latest()->firstOrFail();

        return response(Storage::get($actionPlan->path))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$actionPlan->nama_file.'"');
    }

    public function destroy(ActionPlan $actionPlan): RedirectResponse
    {
        Storage::delete($actionPlan->path);
        $actionPlan->delete();

        return redirect()->route('action-plan.index')->with('status', 'Action Plan berhasil dihapus.');
    }
}
