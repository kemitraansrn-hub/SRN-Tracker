<?php

namespace App\Http\Controllers;

use App\Http\Requests\AkuisisiLeadRequest;
use App\Models\AkuisisiBusinessStatus;
use App\Models\AkuisisiCapitalStatus;
use App\Models\AkuisisiChannel;
use App\Models\AkuisisiFollowupStatus;
use App\Models\AkuisisiInterestLevel;
use App\Models\AkuisisiLead;
use App\Models\AkuisisiPipelineStage;
use App\Models\AkuisisiPriority;
use App\Support\AkuisisiLeadFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Akuisisi > Database Lead -- CRM lead calon mitra, diporting dari app
 * standalone PartnerAcquire CRM (DashboardController + LeadController di
 * situ digabung jadi satu controller, sesuai gaya SRN Tracker). Lihat
 * App\Support\AkuisisiLeadFilters buat aturan filter/tab/urutan.
 */
class AkuisisiLeadController extends Controller
{
    private const PER_PAGE = 25;

    /** Kunci session alamat Database Lead terakhir (tab & filter), tujuan kembali setelah catat follow-up. */
    public const LAST_URL_KEY = 'akuisisi-leads.last_url';

    public function index(Request $request): View
    {
        $filters = AkuisisiLeadFilters::fromRequest($request);
        $request->session()->put(self::LAST_URL_KEY, $request->fullUrl());

        $leads = $filters->orderForTab($filters->applyTab($filters->applyFilters(AkuisisiLead::query())))
            ->with(['channel', 'priority', 'pipelineStage', 'latestFollowupStatus'])
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $tabCounts = collect(AkuisisiLeadFilters::TABS)->mapWithKeys(fn (string $tab) => [
            $tab => $filters->applyTab($filters->applyFilters(AkuisisiLead::query()), $tab)->count(),
        ]);

        return view('akuisisi.leads.index', [
            'filters' => $filters,
            'leads' => $leads,
            'tabCounts' => $tabCounts,
            // Lead sesuai filter di semua tab (To-Do beririsan dengan Aktif/Arsip, jadi tidak dijumlahkan).
            'filteredTotal' => $filters->applyFilters(AkuisisiLead::query())->count(),
            'stats' => $this->stats($filters),
            'matrix' => $matrix = $this->pipelineMatrix($filters),
            'funnel' => $this->pipelineFunnel($matrix),
            'priorities' => AkuisisiPriority::orderBy('sort_order')->get(),
            'pipelineStages' => AkuisisiPipelineStage::orderBy('sort_order')->get(),
            'statuses' => AkuisisiFollowupStatus::orderBy('sort_order')->get(),
            'closingStatusId' => AkuisisiFollowupStatus::where('is_closed_deal', true)->value('id'),
        ]);
    }

    public function create(): View
    {
        $lead = new AkuisisiLead([
            'entry_date' => today(),
            'pipeline_stage_id' => AkuisisiPipelineStage::where('code', 'PROFILING')->value('id'),
        ]);

        return view('akuisisi.leads.create', ['lead' => $lead, ...$this->formOptions()]);
    }

    public function store(AkuisisiLeadRequest $request): RedirectResponse
    {
        $lead = AkuisisiLead::create([
            ...$request->leadData(),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('akuisisi-leads.index')
            ->with('status', "Lead \"{$lead->display_name}\" berhasil ditambahkan.");
    }

    public function show(AkuisisiLead $lead): View
    {
        $lead->load([
            'channel', 'businessStatus', 'interestLevel', 'capitalStatus', 'priority',
            'pipelineStage', 'latestFollowupStatus',
            'followups' => fn ($query) => $query->with(['status', 'pipelineStage', 'priority', 'creator'])
                ->orderByDesc('followup_date')
                ->orderByDesc('id'),
        ]);

        return view('akuisisi.leads.show', [
            'lead' => $lead,
            'statuses' => AkuisisiFollowupStatus::orderBy('sort_order')->get(),
            'pipelineStages' => AkuisisiPipelineStage::orderBy('sort_order')->get(),
        ]);
    }

    public function edit(AkuisisiLead $lead): View
    {
        return view('akuisisi.leads.edit', [
            'lead' => $lead,
            'pipelineLocked' => $lead->followups()->exists(),
            ...$this->formOptions(),
        ]);
    }

    public function update(AkuisisiLeadRequest $request, AkuisisiLead $lead): RedirectResponse
    {
        $lead->update([
            ...$request->leadData(),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('akuisisi-leads.index')
            ->with('status', "Lead \"{$lead->display_name}\" berhasil diperbarui.");
    }

    public function destroy(Request $request, AkuisisiLead $lead): RedirectResponse
    {
        $lead->update(['updated_by' => $request->user()->id]);
        $lead->delete();

        return redirect()->route('akuisisi-leads.index')
            ->with('status', "Lead \"{$lead->display_name}\" berhasil dihapus.");
    }

    public function restore(Request $request, AkuisisiLead $lead): RedirectResponse
    {
        $lead->restore();
        $lead->update(['updated_by' => $request->user()->id]);

        return redirect()->route('akuisisi-leads.edit', $lead)
            ->with('status', "Lead \"{$lead->display_name}\" berhasil dipulihkan.");
    }

    private function formOptions(): array
    {
        return [
            'channels' => AkuisisiChannel::where('is_active', true)->orderBy('sort_order')->get(),
            'businessStatuses' => AkuisisiBusinessStatus::orderBy('sort_order')->get(),
            'interestLevels' => AkuisisiInterestLevel::orderBy('sort_order')->get(),
            'capitalStatuses' => AkuisisiCapitalStatus::orderBy('sort_order')->get(),
            'priorities' => AkuisisiPriority::orderBy('sort_order')->get(['code', 'name', 'min_score', 'max_score']),
            'pipelineStages' => AkuisisiPipelineStage::orderBy('sort_order')->get(),
        ];
    }

    /**
     * Kartu rekap. Mengikuti periode tanggal masuk, kecuali follow-up hari ini (selalu hari ini).
     * Hot & Warm Leads dihitung dari lead aktif agar sama dengan daftar yang dibuka kartunya.
     */
    private function stats(AkuisisiLeadFilters $filters): array
    {
        $inPeriod = fn () => $filters->applyPeriod(AkuisisiLead::query());
        $today = today()->toDateString();

        return [
            'total' => $inPeriod()->count(),
            'todo_today' => AkuisisiLead::where('next_followup_date', $today)->count(),
            'todo_overdue' => AkuisisiLead::where('next_followup_date', '<', $today)->count(),
            'hot' => $filters->whereActive($inPeriod())->where('priority_id', AkuisisiPriority::where('code', 'HOT')->value('id'))->count(),
            'warm' => $filters->whereActive($inPeriod())->where('priority_id', AkuisisiPriority::where('code', 'WARM')->value('id'))->count(),
            'closed' => $inPeriod()->whereIn('latest_followup_status_id', AkuisisiFollowupStatus::where('is_closed_deal', true)->pluck('id'))->count(),
            'active' => $filters->whereActive($inPeriod())->count(),
        ];
    }

    /**
     * Funnel dari matriks tahapan. "Mencapai" suatu tahapan = lead yang tahapannya saat ini di tahap itu
     * atau sesudahnya (tahapan hanya maju). Lead tanpa tahapan (data lama) belum masuk funnel dan
     * dirangkum terpisah. Closing hanya menghitung lead yang sudah ditahapkan.
     */
    private function pipelineFunnel(array $matrix): array
    {
        $closedIds = $matrix['columns']->where('is_closed_deal', true)->pluck('id')->all();
        $droppedIds = $matrix['columns']->where('is_closed_deal', false)->pluck('id')->all();
        $sum = fn (array $cells, array $keys) => array_sum(array_intersect_key($cells, array_flip($keys)));

        $unstaged = $matrix['rows']->firstWhere('key', AkuisisiLeadFilters::NONE);
        $stages = $matrix['rows']->reject(fn (array $row) => $row['key'] === AkuisisiLeadFilters::NONE)->values();

        $steps = [];
        $previous = null;
        foreach ($stages as $index => $stage) {
            $reached = $stages->slice($index)->sum('total');
            $steps[] = [
                'name' => $stage['name'],
                'reached' => $reached,
                'conversion' => $previous ? $reached / $previous : null,
                'active' => $stage['cells']['active'] ?? 0,
                'dropped' => $sum($stage['cells'], $droppedIds),
                'closed' => $sum($stage['cells'], $closedIds),
                'is_closing' => false,
            ];
            $previous = $reached;
        }

        $closed = $stages->sum(fn (array $stage) => $sum($stage['cells'], $closedIds));
        $steps[] = [
            'name' => 'Closing',
            'reached' => $closed,
            // Closing bisa terjadi sebelum Pitching; persentase di atas 100% tidak bermakna.
            'conversion' => $previous && $closed <= $previous ? $closed / $previous : null,
            'active' => 0,
            'dropped' => 0,
            'closed' => $closed,
            'is_closing' => true,
        ];

        return [
            'steps' => $steps,
            'base' => $steps[0]['reached'] ?? 0,
            'total' => $matrix['rows']->sum('total'),
            'unstaged' => $unstaged['total'] ?? 0,
            'unstaged_closed' => $unstaged ? $sum($unstaged['cells'], $closedIds) : 0,
        ];
    }

    /**
     * Posisi lead per tahapan pipeline: kolom "Aktif" + tiap status arsip (Closing dulu), dalam periode terpilih.
     */
    private function pipelineMatrix(AkuisisiLeadFilters $filters): array
    {
        $archived = AkuisisiFollowupStatus::where('is_archived', true)
            ->orderByDesc('is_closed_deal')
            ->orderBy('sort_order')
            ->get();

        $counts = $filters->applyPeriod(AkuisisiLead::query())
            ->selectRaw('pipeline_stage_id, latest_followup_status_id, COUNT(*) AS total')
            ->groupBy('pipeline_stage_id', 'latest_followup_status_id')
            ->get();

        $archivedIds = $archived->pluck('id')->all();
        $cells = [];

        foreach ($counts as $row) {
            $stage = $row->pipeline_stage_id ?? AkuisisiLeadFilters::NONE;
            $column = in_array($row->latest_followup_status_id, $archivedIds) ? $row->latest_followup_status_id : 'active';
            $cells[$stage][$column] = ($cells[$stage][$column] ?? 0) + $row->total;
        }

        $rows = AkuisisiPipelineStage::orderBy('sort_order')->get()
            ->map(fn (AkuisisiPipelineStage $stage) => ['key' => (string) $stage->id, 'name' => $stage->name])
            // Urutan alur funnel: yang belum ditahapkan paling atas.
            ->when(isset($cells[AkuisisiLeadFilters::NONE]), fn (Collection $rows) => $rows->prepend(['key' => AkuisisiLeadFilters::NONE, 'name' => 'Belum ada tahapan']));

        return [
            'columns' => $archived,
            'rows' => $rows->map(fn (array $row) => [
                ...$row,
                'cells' => $cells[$row['key']] ?? [],
                'total' => array_sum($cells[$row['key']] ?? []),
            ]),
        ];
    }
}
