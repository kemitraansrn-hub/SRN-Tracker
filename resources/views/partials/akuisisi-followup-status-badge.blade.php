{{-- Expects: $status (AkuisisiFollowupStatus|null) --}}
@if ($status)
    @php
        $statusClass = match ($status->code) {
            'CLOSING' => 'chip-good',
            'DISKUSI' => 'chip-accent',
            'POSTPONED' => 'chip-warn',
            'INVALID_NUMBER' => 'chip-critical',
            'NOT_INTERESTED', 'NO_CAPITAL' => 'chip-highlight',
            default => 'chip-neutral',
        };
    @endphp
    <span class="chip {{ $statusClass }}">{{ $status->name }}</span>
@else
    <span style="font-size:11.5px; color:var(--ink-faint);">Belum di-follow-up</span>
@endif
