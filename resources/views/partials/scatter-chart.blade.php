{{--
    Reusable inline-SVG scatter plot dengan garis tren (regresi linear
    sederhana), no JS library needed.
    Expects: $points = [['label' => string, 'x' => float, 'y' => float], ...]
    Optional: $xLabel, $yLabel, $width (px), $height (px), $pointColor, $formatX (closure), $formatY (closure)

    Catatan: prop warna sengaja dinamai $pointColor (bukan $color) — nama
    generik kayak $color gampang ketiban variabel yang kebetulan sudah
    dipakai di view pemanggil (@include mewarisi seluruh scope parent).
--}}
@php
    $width = $width ?? 560;
    $height = $height ?? 320;
    $chartColor = $pointColor ?? 'var(--accent)';
    $formatX = $formatX ?? fn ($v) => number_format($v, 0, ',', '.');
    $formatY = $formatY ?? fn ($v) => number_format($v, 0, ',', '.');

    $padLeft = 54; $padRight = 24; $padTop = 20; $padBottom = 40;
    $plotW = $width - $padLeft - $padRight;
    $plotH = $height - $padTop - $padBottom;

    $xs = collect($points)->pluck('x');
    $ys = collect($points)->pluck('y');
    $n = $xs->count();

    $minX = 0;
    $maxX = max($xs->max() ?? 1, 1) * 1.15;
    $minY = 0;
    $maxY = max($ys->max() ?? 1, 1) * 1.15;
    $spanX = ($maxX - $minX) ?: 1;
    $spanY = ($maxY - $minY) ?: 1;

    $toPx = fn ($x) => $padLeft + (($x - $minX) / $spanX) * $plotW;
    $toPy = fn ($y) => $padTop + $plotH - (($y - $minY) / $spanY) * $plotH;

    // Regresi linear sederhana (least squares) buat garis tren.
    $trend = null;
    if ($n >= 2) {
        $sumX = $xs->sum(); $sumY = $ys->sum();
        $sumXY = collect($points)->sum(fn ($p) => $p['x'] * $p['y']);
        $sumX2 = $xs->sum(fn ($v) => $v * $v);
        $denom = ($n * $sumX2 - $sumX * $sumX);
        if (abs($denom) > 0.0001) {
            $slope = ($n * $sumXY - $sumX * $sumY) / $denom;
            $intercept = ($sumY - $slope * $sumX) / $n;
            $trend = [
                'x1' => $minX, 'y1' => max($minY, min($maxY, $intercept + $slope * $minX)),
                'x2' => $maxX, 'y2' => max($minY, min($maxY, $intercept + $slope * $maxX)),
            ];
        }
    }

    $ticksY = 4;
@endphp
<div>
    <svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" style="max-width:100%; height:auto;">
        {{-- Gridlines horizontal + label sumbu Y --}}
        @for ($i = 0; $i <= $ticksY; $i++)
            @php
                $gy = $padTop + $plotH - ($i / $ticksY) * $plotH;
                $val = $minY + ($i / $ticksY) * $spanY;
            @endphp
            <line x1="{{ $padLeft }}" y1="{{ round($gy, 1) }}" x2="{{ $width - $padRight }}" y2="{{ round($gy, 1) }}" stroke="var(--line)" stroke-width="1"/>
            <text x="{{ $padLeft - 8 }}" y="{{ round($gy, 1) + 3 }}" text-anchor="end" font-size="9.5" fill="var(--ink-muted)">{{ $formatY($val) }}</text>
        @endfor

        {{-- Sumbu X --}}
        <line x1="{{ $padLeft }}" y1="{{ $padTop + $plotH }}" x2="{{ $width - $padRight }}" y2="{{ $padTop + $plotH }}" stroke="var(--ink-faint)" stroke-width="1.2"/>
        <line x1="{{ $padLeft }}" y1="{{ $padTop }}" x2="{{ $padLeft }}" y2="{{ $padTop + $plotH }}" stroke="var(--ink-faint)" stroke-width="1.2"/>

        @php $ticksX = 4; @endphp
        @for ($i = 0; $i <= $ticksX; $i++)
            @php
                $gx = $padLeft + ($i / $ticksX) * $plotW;
                $val = $minX + ($i / $ticksX) * $spanX;
            @endphp
            <text x="{{ round($gx, 1) }}" y="{{ $padTop + $plotH + 16 }}" text-anchor="middle" font-size="9.5" fill="var(--ink-muted)">{{ $formatX($val) }}</text>
        @endfor

        {{-- Garis tren --}}
        @if ($trend)
            <line x1="{{ round($toPx($trend['x1']), 1) }}" y1="{{ round($toPy($trend['y1']), 1) }}"
                  x2="{{ round($toPx($trend['x2']), 1) }}" y2="{{ round($toPy($trend['y2']), 1) }}"
                  stroke="{{ $chartColor }}" stroke-width="1.5" stroke-dasharray="5 4" opacity="0.55"/>
        @endif

        {{-- Titik data --}}
        @foreach ($points as $p)
            @php $px = $toPx($p['x']); $py = $toPy($p['y']); @endphp
            <circle cx="{{ round($px, 1) }}" cy="{{ round($py, 1) }}" r="6" fill="{{ $chartColor }}" opacity="0.85" stroke="var(--surface)" stroke-width="2"/>
            <text x="{{ round($px, 1) }}" y="{{ round($py, 1) - 11 }}" text-anchor="middle" font-size="10.5" font-weight="600" fill="var(--ink)">{{ $p['label'] }}</text>
        @endforeach

        {{-- Judul sumbu --}}
        @if (! empty($yLabel))
            <text x="{{ -($padTop + $plotH / 2) }}" y="12" text-anchor="middle" font-size="10.5" fill="var(--ink-muted)" transform="rotate(-90)">{{ $yLabel }}</text>
        @endif
        @if (! empty($xLabel))
            <text x="{{ $padLeft + $plotW / 2 }}" y="{{ $height - 4 }}" text-anchor="middle" font-size="10.5" fill="var(--ink-muted)">{{ $xLabel }}</text>
        @endif
    </svg>
</div>
