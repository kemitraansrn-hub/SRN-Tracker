@extends('layouts.app')

@section('content')
    @php
        use App\Services\AkuisisiLeadImport\AkuisisiLeadImportValidator as V;

        $problems = collect($result['problems']);
        $errorCount = $problems->where('type', V::TYPE_ERROR)->count();
        $skippedCount = $problems->count() - $errorCount;
        $validCount = count($result['valid']);
        $overwriteCount = collect($result['valid'])->filter(fn ($row) => ! empty($row['overwrite_lead_id']))->count();
        $scoring = app(\App\Services\AkuisisiLeadScoring::class);
        $typeLabels = [
            V::TYPE_ERROR => ['Error', 'chip-critical'],
            V::TYPE_EXISTING => ['Sudah ada', 'chip-warn'],
            V::TYPE_DUPLICATE => ['Dobel di file', 'chip-warn'],
        ];
        $shownProblems = 200;
    @endphp

    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:16px;">
        <div>
            <h1 class="display" style="font-size:24px;">Pratinjau Import</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">{{ $fileName }} &middot; belum ada data yang disimpan</div>
        </div>
        <div style="display:flex; gap:8px;">
            <form method="POST" action="{{ route('akuisisi-leads.imports.cancel', $token) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn" style="width:auto;">Batal</button>
            </form>
            @if ($validCount)
                @php
                    $confirmMsg = $overwriteCount
                        ? "Import {$validCount} lead ({$overwriteCount} di antaranya akan MENIMPA data lead lama yang sudah ada)?"
                        : "Import {$validCount} lead?";
                @endphp
                <form method="POST" action="{{ route('akuisisi-leads.imports.confirm', $token) }}" onsubmit="return confirm('{{ $confirmMsg }}');">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="width:auto;">Import {{ number_format($validCount, 0, ',', '.') }} Lead</button>
                </form>
            @endif
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:16px;">
        @foreach (array_filter([
            ['Baris di file', $result['total'], 'var(--ink)'],
            ['Siap diimport', $validCount, 'var(--good)'],
            $overwriteCount ? ['Akan menimpa data lama', $overwriteCount, 'var(--warn)'] : null,
            ['Error (tidak disimpan)', $errorCount, 'var(--critical)'],
            ['Dilewati (sudah ada / dobel)', $skippedCount, 'var(--warn)'],
        ]) as [$label, $value, $color])
            <div class="card" style="padding:14px 16px;">
                <div style="font-size:11.5px; color:var(--ink-muted); font-weight:600;">{{ $label }}</div>
                <div class="tnum" style="font-size:22px; font-weight:700; color:{{ $color }}; margin-top:4px;">{{ number_format($value, 0, ',', '.') }}</div>
            </div>
        @endforeach
    </div>

    @if ($result['unknown_headers'])
        <div class="alert-success" style="background:var(--warn-soft); color:var(--warn); margin-bottom:16px;">
            Kolom berikut tidak dikenal dan diabaikan: <strong>{{ implode(', ', $result['unknown_headers']) }}</strong>.
        </div>
    @endif

    @if ($problems->isNotEmpty())
        <section class="card table-card" style="padding:0; margin-bottom:16px;">
            <div class="card-head" style="padding:16px;">
                <div>
                    <div class="card-title">Baris bermasalah ({{ number_format($problems->count(), 0, ',', '.') }})</div>
                    <div class="card-hint">Tidak ikut diimport. Unduh, perbaiki, lalu upload ulang sebagai file baru.</div>
                </div>
                <a href="{{ route('akuisisi-leads.imports.pending-errors', $token) }}" class="btn" style="width:auto;">Unduh Baris Bermasalah</a>
            </div>
            <div class="table-scroll" style="max-height:28rem;">
                <table>
                    <thead>
                        <tr>
                            <th>Baris</th>
                            <th>Jenis</th>
                            <th>Nama Lead</th>
                            <th>Nomor WhatsApp</th>
                            <th>Alasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($problems->take($shownProblems) as $problem)
                            <tr>
                                <td class="tnum" style="color:var(--ink-muted);">{{ $problem['row'] }}</td>
                                <td><span class="chip {{ $typeLabels[$problem['type']][1] }}">{{ $typeLabels[$problem['type']][0] }}</span></td>
                                <td>{{ $problem['values']['Nama Lead'] ?? '–' }}</td>
                                <td style="color:var(--ink-muted);">{{ $problem['values']['Nomor WhatsApp'] ?? '–' }}</td>
                                <td style="white-space:normal; word-break:break-word;">{{ implode('; ', $problem['messages']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($problems->count() > $shownProblems)
                <div style="border-top:1px solid var(--line); padding:12px 16px; font-size:11.5px; color:var(--ink-muted);">Menampilkan {{ $shownProblems }} baris pertama; semua baris ada di file unduhan.</div>
            @endif
        </section>
    @endif

    @if ($validCount)
        <section class="card table-card" style="padding:0;">
            <div style="padding:16px; border-bottom:1px solid var(--line);">
                <div class="card-title">Siap diimport ({{ number_format($validCount, 0, ',', '.') }})</div>
                <div class="card-hint">Contoh {{ min(20, $validCount) }} baris pertama, dengan prioritas yang akan dihitung sistem.</div>
            </div>
            <div class="table-scroll" style="max-height:none;">
                <table>
                    <thead>
                        <tr>
                            <th>Baris</th>
                            <th>Tanggal Masuk</th>
                            <th>Nama Lead</th>
                            <th>Nomor WhatsApp</th>
                            <th>Channel</th>
                            <th>Prioritas</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($result['valid'], 0, 20) as $row)
                            @php
                                $a = $row['attributes'];
                                $score = $scoring->score($a['business_status_id'], $a['interest_level_id'], $a['capital_status_id']);
                                $priority = $scoring->priorityFor($score);
                            @endphp
                            <tr>
                                <td class="tnum" style="color:var(--ink-muted);">{{ $row['row'] }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($a['entry_date'])->translatedFormat('d M Y') }}</td>
                                <td style="font-weight:600;">{{ $a['name'] }}</td>
                                <td style="color:var(--good);">{{ $a['whatsapp'] }}</td>
                                <td>{{ $row['values']['Channel Sumber'] }}</td>
                                <td>
                                    @include('partials.akuisisi-priority-badge', ['priority' => $priority])
                                    <span style="font-size:11px; color:var(--ink-muted);">{{ $score }} poin</span>
                                </td>
                                <td>
                                    @if (! empty($row['overwrite_lead_id']))
                                        <span class="chip chip-warn">Akan ditimpa</span>
                                    @elseif ($a['is_legacy'])
                                        <span class="chip chip-neutral">Data lama</span>
                                    @else
                                        <span class="chip chip-accent">Lead baru</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <div class="card" style="text-align:center; padding:40px 16px;">
            <div style="font-weight:600;">Tidak ada baris yang bisa diimport.</div>
            <div style="font-size:12.5px; color:var(--ink-muted); margin-top:4px;">Perbaiki baris bermasalah lalu upload ulang.</div>
        </div>
    @endif
@endsection
