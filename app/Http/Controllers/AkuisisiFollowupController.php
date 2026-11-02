<?php

namespace App\Http\Controllers;

use App\Http\Requests\AkuisisiFollowupRequest;
use App\Models\AkuisisiFollowup;
use App\Models\AkuisisiFollowupStatus;
use App\Models\AkuisisiInterestLevel;
use App\Models\AkuisisiLead;
use App\Models\AkuisisiPipelineStage;
use App\Models\AkuisisiPriority;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Diporting dari App\Http\Controllers\FollowupController di PartnerAcquire CRM. */
class AkuisisiFollowupController extends Controller
{
    public function store(AkuisisiFollowupRequest $request, AkuisisiLead $lead): RedirectResponse
    {
        $this->applyProposalInterest($request, $lead);

        $lead->followups()->create([
            ...$request->followupData($lead->priority),
            'priority_id' => $lead->priority_id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        // Kembali ke Database Lead dengan tab & filter terakhir agar CS bisa lanjut ke lead berikutnya.
        return redirect()->to($request->session()->get(AkuisisiLeadController::LAST_URL_KEY, route('akuisisi-leads.index')))
            ->with('status', "Follow-up untuk {$lead->display_name} berhasil dicatat.");
    }

    public function edit(AkuisisiFollowup $followup): View
    {
        $this->ensureLeadExists($followup);

        return view('akuisisi.followups.edit', [
            'followup' => $followup->load('lead'),
            'statuses' => AkuisisiFollowupStatus::orderBy('sort_order')->get(),
            'pipelineStages' => AkuisisiPipelineStage::orderBy('sort_order')->get(),
            'schedulePriority' => $this->schedulePriority($followup),
        ]);
    }

    public function update(AkuisisiFollowupRequest $request, AkuisisiFollowup $followup): RedirectResponse
    {
        $this->ensureLeadExists($followup);

        $followup->update([
            ...$request->followupData($this->schedulePriority($followup)),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('akuisisi-leads.show', $followup->lead_id)
            ->with('status', 'Follow-up berhasil diperbarui.');
    }

    public function destroy(AkuisisiFollowup $followup): RedirectResponse
    {
        $this->ensureLeadExists($followup);

        $followup->delete();

        return redirect()->route('akuisisi-leads.show', $followup->lead_id)
            ->with('status', 'Follow-up berhasil dihapus.');
    }

    /**
     * Naik ke tahap Pitching adalah sinyal "Minta Proposal": bila CS mencentang sarannya,
     * penilaian Respon/Minat lead diperbarui lebih dulu agar skor & jadwal memakai prioritas terbaru.
     */
    private function applyProposalInterest(AkuisisiFollowupRequest $request, AkuisisiLead $lead): void
    {
        $stage = AkuisisiPipelineStage::find($request->validated('pipeline_stage_id'));

        if (! $request->boolean('set_interest_proposal') || $stage?->code !== 'PITCHING') {
            return;
        }

        $lead->update([
            'interest_level_id' => AkuisisiInterestLevel::where('code', 'PROPOSAL')->value('id'),
            'updated_by' => $request->user()->id,
        ]);
        $lead->load('priority');
    }

    /** Prioritas acuan jadwal: prioritas saat follow-up dicatat, atau prioritas lead sekarang. */
    private function schedulePriority(AkuisisiFollowup $followup): ?AkuisisiPriority
    {
        return $followup->priority ?? $followup->lead->priority;
    }

    /** Riwayat milik lead yang sudah dihapus tidak bisa diubah. */
    private function ensureLeadExists(AkuisisiFollowup $followup): void
    {
        abort_if($followup->lead === null, 404);
    }
}
