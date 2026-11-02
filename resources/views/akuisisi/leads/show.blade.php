@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:12px;">
        <a href="{{ route('akuisisi-leads.index') }}" style="font-size:13px; color:var(--ink-muted); text-decoration:none;">&larr; Kembali ke daftar</a>
        @if (auth()->user()->hasAdminAccess())
            <a href="{{ route('akuisisi-leads.edit', $lead) }}" class="btn" style="width:auto;">Edit Lead</a>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success" style="margin-bottom:16px;">{{ session('status') }}</div>
    @endif

    <div style="display:grid; grid-template-columns:320px 1fr; gap:20px; align-items:start;">
        {{-- Info lead --}}
        <aside class="card">
            <h1 style="font-size:17px; font-weight:700; {{ $lead->name ? 'color:var(--ink);' : 'font-style:italic; color:var(--ink-muted);' }}">{{ $lead->display_name }}</h1>
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:4px; font-size:12.5px; color:var(--ink-muted);">
                @if ($lead->city) <span>{{ $lead->city }}</span> <span>&middot;</span> @endif
                @include('partials.akuisisi-whatsapp-link', ['number' => $lead->whatsapp])
            </div>
            @if ($lead->is_legacy)
                <span class="chip chip-neutral" style="margin-top:8px;">Data lama (migrasi)</span>
            @endif

            <dl style="margin-top:16px; border-top:1px solid var(--line); padding-top:14px; font-size:12.5px;">
                <div style="display:flex; justify-content:space-between; gap:10px; margin-bottom:10px;">
                    <dt style="color:var(--ink-muted);">Prioritas</dt>
                    <dd style="display:flex; align-items:center; gap:6px;">
                        @include('partials.akuisisi-priority-badge', ['priority' => $lead->priority])
                        @if ($lead->score !== null) <span style="color:var(--ink-muted);">{{ $lead->score }} poin</span> @endif
                    </dd>
                </div>
                @php
                    $rows = [
                        'Tanggal masuk' => $lead->entry_date->translatedFormat('d M Y'),
                        'Channel' => $lead->channel->name,
                        'Pipeline' => $lead->pipelineStage?->name,
                        'Status bisnis' => $lead->businessStatus?->name,
                        'Respon / minat' => $lead->interestLevel?->name,
                        'Status modal' => $lead->capitalStatus?->name,
                    ];
                @endphp
                @foreach ($rows as $label => $value)
                    <div style="display:flex; justify-content:space-between; gap:10px; margin-bottom:8px;">
                        <dt style="color:var(--ink-muted);">{{ $label }}</dt>
                        <dd style="text-align:right; font-weight:600; color:var(--ink);">{{ $value ?? '–' }}</dd>
                    </div>
                @endforeach
            </dl>

            <dl style="margin-top:4px; border-top:1px solid var(--line); padding-top:14px; font-size:12.5px;">
                <div style="display:flex; justify-content:space-between; gap:10px; margin-bottom:10px;">
                    <dt style="color:var(--ink-muted);">Hasil terakhir</dt>
                    <dd style="display:flex; flex-direction:column; align-items:flex-end; gap:4px;">
                        @include('partials.akuisisi-followup-status-badge', ['status' => $lead->latestFollowupStatus])
                        @include('partials.akuisisi-no-response-streak', ['lead' => $lead])
                    </dd>
                </div>
                <div style="display:flex; justify-content:space-between; gap:10px; margin-bottom:8px;">
                    <dt style="color:var(--ink-muted);">Last follow-up</dt>
                    <dd style="font-weight:600;">{{ $lead->last_followup_date?->translatedFormat('d M Y') ?? '–' }}</dd>
                </div>
                <div style="display:flex; justify-content:space-between; gap:10px;">
                    <dt style="color:var(--ink-muted);">Next follow-up</dt>
                    @php
                        $nextColor = $lead->next_followup_date?->isToday() ? 'var(--warn)' : ($lead->next_followup_date?->isPast() ? 'var(--critical)' : 'var(--ink)');
                    @endphp
                    <dd style="font-weight:700; color:{{ $nextColor }};">{{ $lead->next_followup_date?->translatedFormat('d M Y') ?? 'Tidak dijadwalkan' }}</dd>
                </div>
            </dl>
        </aside>

        <div style="display:flex; flex-direction:column; gap:16px;">
            {{-- Catat follow-up baru --}}
            @if (auth()->user()->hasAdminAccess())
                <form method="POST" action="{{ route('akuisisi-leads.followups.store', $lead) }}" class="card" id="catat-followup">
                    @csrf
                    <div class="card-title" style="margin-bottom:16px;">Catat Follow-up</div>

                    @include('akuisisi.followups._form', [
                        'followup' => new \App\Models\AkuisisiFollowup(['followup_date' => today(), 'pipeline_stage_id' => $lead->pipeline_stage_id]),
                        'schedulePriority' => $lead->priority,
                    ])

                    <div style="display:flex; justify-content:flex-end; margin-top:16px;">
                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan Follow-up</button>
                    </div>
                </form>
            @endif

            {{-- Riwayat --}}
            <section class="card">
                <div class="card-title">Riwayat Follow-up <span style="color:var(--ink-faint); font-weight:500;">({{ $lead->followups->count() }})</span></div>

                @if ($lead->followups->isEmpty())
                    <div style="text-align:center; color:var(--ink-muted); font-size:12.5px; padding:30px 16px; border:1px dashed var(--line); border-radius:8px; margin-top:12px;">
                        Belum ada riwayat follow-up.
                        @if ($lead->is_legacy && $lead->latestFollowupStatus) Status saat ini berasal dari data migrasi. @endif
                    </div>
                @else
                    <div style="margin-top:14px;">
                        @foreach ($lead->followups as $followup)
                            @php $previousStage = $lead->followups->get($loop->index + 1)?->pipelineStage; @endphp
                            <div style="position:relative; border-left:2px solid {{ $loop->first ? 'var(--accent)' : 'var(--line)' }}; padding:0 0 18px 16px; {{ $loop->last ? 'padding-bottom:0;' : '' }}">
                                <span style="position:absolute; top:3px; left:-6px; width:10px; height:10px; border-radius:50%; background:{{ $loop->first ? 'var(--accent)' : 'var(--ink-faint)' }}; box-shadow:0 0 0 3px var(--surface);"></span>

                                <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:8px;">
                                    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px;">
                                        <span style="font-size:13px; font-weight:700;">{{ $followup->followup_date->translatedFormat('l, d M Y') }}</span>
                                        @include('partials.akuisisi-followup-status-badge', ['status' => $followup->status])
                                        @if ($loop->first)
                                            <span style="font-size:11px; font-weight:600; color:var(--accent-ink);">Terbaru</span>
                                        @endif
                                    </div>
                                    @if (auth()->user()->hasAdminAccess())
                                        <div style="display:flex; gap:4px;">
                                            <a href="{{ route('akuisisi-leads.followups.edit', $followup) }}" class="btn" style="width:auto; font-size:11px; padding:4px 8px;">Edit</a>
                                            <form method="POST" action="{{ route('akuisisi-leads.followups.destroy', $followup) }}" onsubmit="return confirm('Hapus follow-up tanggal {{ $followup->followup_date->translatedFormat('d M Y') }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn" style="width:auto; font-size:11px; padding:4px 8px;">Hapus</button>
                                            </form>
                                        </div>
                                    @endif
                                </div>

                                @if ($followup->note)
                                    <p style="margin-top:6px; font-size:12.5px; color:var(--ink); white-space:pre-line;">{{ $followup->note }}</p>
                                @endif

                                @if ($followup->pipelineStage)
                                    <p style="margin-top:6px; font-size:11.5px; color:var(--ink-muted);">
                                        Pipeline:
                                        @if ($previousStage && $previousStage->isNot($followup->pipelineStage))
                                            {{ $previousStage->name }} &rarr; <strong style="color:var(--accent-ink);">{{ $followup->pipelineStage->name }}</strong>
                                        @else
                                            <strong style="color:var(--ink);">{{ $followup->pipelineStage->name }}</strong>
                                        @endif
                                    </p>
                                @endif

                                <p style="margin-top:6px; font-size:11.5px; color:var(--ink-muted);">
                                    Next: <strong style="color:var(--ink);">{{ $followup->next_followup_date?->translatedFormat('d M Y') ?? 'tidak dijadwalkan' }}</strong>
                                    @if ($followup->is_next_manual && $followup->next_followup_date) (manual) @endif
                                    @if ($followup->priority) &middot; prioritas saat itu {{ $followup->priority->name }} @endif
                                    @if ($followup->creator) &middot; oleh {{ $followup->creator->name }} @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
