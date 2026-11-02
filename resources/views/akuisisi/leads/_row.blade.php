<tr>
    <td>
        <div style="display:flex; flex-direction:column; gap:3px;">
            <a href="{{ route('akuisisi-leads.show', $lead) }}" style="font-size:13px; font-weight:600; text-decoration:none; {{ $lead->name ? 'color:var(--ink);' : 'font-style:italic; color:var(--ink-muted);' }}">{{ $lead->display_name }}</a>
            <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                @include('partials.akuisisi-whatsapp-link', ['number' => $lead->whatsapp])
                @if ($lead->city)
                    <span style="font-size:11.5px; color:var(--ink-muted);">{{ $lead->city }}</span>
                @endif
            </div>
            <div style="font-size:11px; color:var(--ink-faint);">Masuk {{ $lead->entry_date->translatedFormat('d M Y') }}</div>
        </div>
    </td>
    <td>
        <div style="display:flex; flex-direction:column; gap:3px;">
            <div style="display:flex; align-items:center; gap:6px;">
                @include('partials.akuisisi-priority-badge', ['priority' => $lead->priority])
                @if ($lead->score !== null)
                    <span style="font-size:11px; color:var(--ink-muted);">{{ $lead->score }} poin</span>
                @endif
            </div>
            <div style="font-size:11.5px; color:var(--ink-muted);">{{ $lead->channel->name }}</div>
        </div>
    </td>
    <td>
        <div style="display:flex; flex-direction:column; gap:4px;">
            @if ($lead->pipelineStage)
                <span class="chip chip-accent">{{ $lead->pipelineStage->name }}</span>
            @else
                <span style="color:var(--ink-faint);">–</span>
            @endif
            <div style="display:flex; gap:4px; flex-wrap:wrap;">
                @include('partials.akuisisi-followup-status-badge', ['status' => $lead->latestFollowupStatus])
                @include('partials.akuisisi-no-response-streak', ['lead' => $lead])
            </div>
        </div>
    </td>
    <td>
        @if ($lead->next_followup_date)
            @php
                $due = $lead->next_followup_date;
                $dueClass = match (true) {
                    $due->isToday() => 'chip-warn',
                    $due->isPast() => 'chip-critical',
                    default => 'chip-neutral',
                };
            @endphp
            <span class="chip {{ $dueClass }}">
                {{ $due->translatedFormat('d M Y') }}
                @if ($due->isToday()) (Hari ini) @elseif ($due->isPast()) (Terlewat) @endif
            </span>
        @else
            <span style="color:var(--ink-faint);">–</span>
        @endif
    </td>
    <td style="white-space:normal; word-break:break-word;">
        @if ($lead->latest_note)
            <div style="font-size:12.5px; color:var(--ink); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;" title="{{ $lead->latest_note }}">{{ $lead->latest_note }}</div>
        @else
            <span style="color:var(--ink-faint);">–</span>
        @endif
        @if ($lead->last_followup_date)
            <div style="font-size:11px; color:var(--ink-faint); margin-top:3px;">Terakhir {{ $lead->last_followup_date->translatedFormat('d M Y') }}</div>
        @endif
    </td>
    <td>
        <div style="display:flex; justify-content:flex-end; gap:5px;">
            <a href="{{ route('akuisisi-leads.show', $lead) }}#catat-followup" class="btn btn-primary" style="width:auto; font-size:11px; padding:5px 9px;">Follow-up</a>
            @if (auth()->user()->hasAdminAccess())
                <a href="{{ route('akuisisi-leads.edit', $lead) }}" class="btn" style="width:auto; font-size:11px; padding:5px 9px;">Edit</a>
                <form method="POST" action="{{ route('akuisisi-leads.destroy', $lead) }}" onsubmit="return confirm('Hapus lead &quot;{{ addslashes($lead->display_name) }}&quot;?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn" style="width:auto; font-size:11px; padding:5px 9px;">Hapus</button>
                </form>
            @endif
        </div>
    </td>
</tr>
