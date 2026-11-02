{{-- Expects: $priority (AkuisisiPriority|null) --}}
@php
    $priorityClass = match ($priority?->code ?? null) {
        'HOT' => 'chip-critical',
        'WARM' => 'chip-warn',
        'COLD' => 'chip-accent',
        default => 'chip-neutral',
    };
@endphp
<span class="chip {{ $priorityClass }}">{{ $priority?->name ?? 'Belum dinilai' }}</span>
