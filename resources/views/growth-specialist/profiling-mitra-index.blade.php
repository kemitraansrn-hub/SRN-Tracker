@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px; margin-bottom:4px;">Profiling Mitra</h1>
    <div class="card-hint" style="margin-bottom:20px;">Pilih mitra buat diisi/diubah profilnya.</div>

    <div class="card" style="max-width:480px; margin-bottom:24px;">
        <div class="field">
            <label>Pilih Mitra</label>
            <select id="pilihMitra" onchange="if (this.value) window.location.href = this.value;">
                <option value="">— pilih mitra —</option>
                @foreach ($mitraOptions as $m)
                    <option value="{{ route('growth-specialist.profiling-mitra.edit', $m) }}">{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($sudahDiisi->isNotEmpty())
        <section class="card table-card reveal-on-scroll" style="padding:0;">
            <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
                <div class="card-title">Mitra yang sudah pernah diisi ({{ $sudahDiisi->count() }})</div>
                <div class="card-hint">Diurutkan dari yang terakhir diubah</div>
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Nama Mitra</th><th>Status</th><th>Tipe Mitra</th><th>Terakhir Diubah</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($sudahDiisi as $p)
                            @php $tipeMitra = $p->tipeMitra(); @endphp
                            <tr>
                                <td style="font-weight:600;">{{ $p->mitra->nama }}<div style="font-size:11px; color:var(--ink-faint); font-weight:400;">{{ $p->mitra->kode_mitra }}</div></td>
                                <td>@if($p->status)<span class="chip chip-accent">{{ $p->status }}</span>@else — @endif</td>
                                <td>@if($tipeMitra)<span class="chip {{ $tipeMitra === 'Prioritas' ? 'chip-highlight' : 'chip-neutral' }}">{{ $tipeMitra }}</span>@else — @endif</td>
                                <td>{{ $p->updated_at->format('d/m/Y H:i') }}</td>
                                <td><a href="{{ route('growth-specialist.profiling-mitra.edit', $p->mitra) }}" class="btn" style="width:auto; font-size:11.5px; padding:5px 9px; text-decoration:none; display:inline-block;">Buka</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
