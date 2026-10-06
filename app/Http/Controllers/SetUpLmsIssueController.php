<?php

namespace App\Http\Controllers;

use App\Models\LmsEnrollment;
use App\Models\LmsStep;
use App\Models\Mitra;
use App\Models\SetUpLmsIssue;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Issue (Growth Specialist > Set Up LMS) — log kendala mitra selama
 * mengerjakan LMS (device, tidak respon, waktu, dll), per platform. Mitra
 * yang bisa dipilih cuma yang sudah terdaftar di Set Up LMS (ada di
 * lms_enrollments), bukan semua mitra.
 */
class SetUpLmsIssueController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $q = trim((string) $request->input('q'));
        $platformFilter = trim((string) $request->input('platform'));

        $enrolledMitraIds = LmsEnrollment::distinct()->pluck('mitra_id');
        $mitraOptions = $this->scopedMitra($user)->whereIn('id', $enrolledMitraIds)->orderBy('nama')
            ->get(['id', 'nama', 'kode_mitra'])
            ->map(fn ($m) => (object) ['id' => $m->id, 'label' => $m->nama.' ('.$m->kode_mitra.')']);

        $issues = SetUpLmsIssue::with(['mitra', 'creator'])
            ->whereIn('mitra_id', $this->scopedMitra($user)->pluck('id'))
            ->when($q !== '', fn ($w) => $w->whereHas('mitra', fn ($m) => $m->where('nama', 'like', "%{$q}%")->orWhere('kode_mitra', 'like', "%{$q}%")))
            ->when($platformFilter !== '', fn ($w) => $w->where('platform', $platformFilter))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('set-up-lms.issue', [
            'mitraOptions' => $mitraOptions,
            'platforms' => LmsStep::PLATFORMS,
            'detailOptions' => SetUpLmsIssue::DETAIL_OPTIONS,
            'issues' => $issues,
            'q' => $q,
            'platformFilter' => $platformFilter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'integer'],
            'platform' => ['required', 'in:'.implode(',', array_keys(LmsStep::PLATFORMS))],
            'detail' => ['required', 'in:'.implode(',', SetUpLmsIssue::DETAIL_OPTIONS)],
            'isu_kendala' => ['required', 'string', 'max:2000'],
        ]);

        $mitra = $this->scopedMitra($request->user())->findOrFail($data['mitra_id']);

        abort_unless(LmsEnrollment::where('mitra_id', $mitra->id)->exists(), 422, 'Mitra ini belum terdaftar di Set Up LMS.');

        SetUpLmsIssue::create([
            'mitra_id' => $mitra->id,
            'platform' => $data['platform'],
            'detail' => $data['detail'],
            'isu_kendala' => $data['isu_kendala'],
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('growth-specialist.set-up-lms.issue')->with('status', 'Issue untuk '.$mitra->nama.' tersimpan.');
    }

    public function destroy(SetUpLmsIssue $setUpLmsIssue): RedirectResponse
    {
        $setUpLmsIssue->delete();

        return redirect()->route('growth-specialist.set-up-lms.issue')->with('status', 'Issue dihapus.');
    }

    private function scopedMitra(User $user)
    {
        return Mitra::query()->when($user->role === 'kae', fn ($q) => $q->where('kae_code', $user->kae_code));
    }
}
