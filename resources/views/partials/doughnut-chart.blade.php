{{--
    Reusable inline-SVG doughnut chart, no JS library needed.
    Expects: $segments = [['label' => string, 'pct' => float], ...]
    Optional: $size (px), $strokeWidth (px), $colors (array of CSS color values)
--}}
@php
    $size = $size ?? 150;
    $strokeWidth = $strokeWidth ?? 20;
    $radius = ($size - $strokeWidth) / 2;
    $circumference = 2 * M_PI * $radius;
    $center = $size / 2;
    $colors = $colors ?? ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)', 'var(--chart-6)'];
    $cumulative = 0;
@endphp
<div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" style="flex:none;">
        <circle cx="{{ $center }}" cy="{{ $center }}" r="{{ $radius }}" fill="none" stroke="var(--line)" stroke-width="{{ $strokeWidth }}"/>
        @foreach ($segments as $i => $seg)
            @php
                $pct = (float) $seg['pct'];
                $dash = ($pct / 100) * $circumference;
                $gap = $circumference - $dash;
                $offset = -$cumulative;
                $cumulative += $dash;
                $color = $colors[$i % count($colors)];
            @endphp
            @if ($pct > 0)
                <circle cx="{{ $center }}" cy="{{ $center }}" r="{{ $radius }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $strokeWidth }}"
                    stroke-dasharray="{{ round($dash, 2) }} {{ round($gap, 2) }}" stroke-dashoffset="{{ round($offset, 2) }}"
                    transform="rotate(-90 {{ $center }} {{ $center }})"/>
            @endif
        @endforeach
    </svg>
    <div style="display:flex; flex-direction:column; gap:8px; flex:1; min-width:120px;">
        @forelse ($segments as $i => $seg)
            @php $color = $colors[$i % count($colors)]; @endphp
            <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; font-size:12.5px;">
                <div style="display:flex; align-items:center; gap:8px; min-width:0;">
                    <span style="width:9px; height:9px; border-radius:50%; background:{{ $color }}; flex:none;"></span>
                    <span style="font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $seg['label'] }}</span>
                </div>
                <span class="tnum" style="color:var(--ink-muted); flex:none;">{{ $seg['pct'] }}%</span>
            </div>
        @empty
            <span style="color:var(--ink-faint); font-size:12.5px;">Belum ada data.</span>
        @endforelse
    </div>
</div>
