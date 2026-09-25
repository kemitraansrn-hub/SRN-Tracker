@extends('layouts.app')

@php
    $statusColor = ['Lengkap' => 'good', 'Proses' => 'warn', 'Awal' => 'critical'];
    $platformLabel = $platforms[$tab];
@endphp

@section('content')
    <h1 class="display" style="font-size:24px; margin-bottom:4px;">Set Up LMS</h1>
    <div class="card-hint" style="margin-bottom:16px;">Tracking mitra yang sudah diprofiling dan mengerjakan LMS per platform. Tiap video wajib dicentang dengan link GDrive sebagai bukti.</div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <div style="display:flex; gap:4px; margin-bottom:22px; border-bottom:1px solid var(--line);">
        @foreach ($platforms as $key => $label)
            <a href="{{ route('growth-specialist.set-up-lms', ['tab' => $key]) }}" style="padding:10px 4px; margin-right:22px; text-decoration:none; border-bottom:2px solid {{ $tab === $key ? 'var(--accent)' : 'transparent' }}; font-size:14px; font-weight:{{ $tab === $key ? '700' : '600' }}; color:var(--{{ $tab === $key ? 'ink' : 'ink-muted' }});">{{ $label }}</a>
        @endforeach
    </div>

    @if ($steps->isEmpty())
        <div class="card" style="max-width:560px; color:var(--ink-muted);">
            Belum ada video LMS {{ $platformLabel }} yang diatur.
        </div>
    @else
        <section class="card" id="input" style="max-width:820px; margin-bottom:20px;">
            <div class="card-title" style="margin-bottom:12px;">Input Progres LMS {{ $platformLabel }}</div>

            <div class="field">
                <label>Pilih Mitra</label>
                @include('partials.searchable-select', [
                    'id' => 'pilihMitraLms',
                    'name' => '_picker',
                    'options' => $mitraOptions,
                    'selectedId' => $selectedMitra?->id,
                    'placeholder' => 'Ketik buat cari mitra...',
                    'extraAttr' => 'url',
                    'onchangeJs' => 'gotoPilihanMitraLms',
                ])
            </div>
            <script>
                function gotoPilihanMitraLms(id, url) {
                    if (url) window.location.href = url;
                }
            </script>

            @if ($selectedMitra)
                <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin:16px 0 12px;">
                    <div>
                        <div style="font-weight:700; font-size:15px;">{{ $selectedMitra->nama }}</div>
                        <div style="font-size:11.5px; color:var(--ink-muted);">{{ $selectedMitra->kode_mitra }}</div>
                    </div>
                    <div style="text-align:right;">
                        <span class="chip chip-{{ $statusColor[$selectedStatus] }}">{{ $selectedStatus }}</span>
                        <span class="tnum" style="font-weight:700; margin-left:6px;">{{ $selectedPct }}%</span>
                        <div style="font-size:11.5px; color:var(--ink-muted);">{{ $selectedCompletions->count() }} dari {{ $steps->count() }} video selesai</div>
                    </div>
                </div>
                <div style="height:6px; border-radius:4px; background:var(--line); overflow:hidden; margin-bottom:16px;">
                    <div style="height:100%; border-radius:4px; background:var(--accent); width:{{ $selectedPct }}%;"></div>
                </div>

                @unless ($isEnrolled)
                    <form method="POST" action="{{ route('growth-specialist.set-up-lms.enroll') }}" style="margin-bottom:14px;">
                        @csrf
                        <input type="hidden" name="mitra_id" value="{{ $selectedMitra->id }}">
                        <input type="hidden" name="platform" value="{{ $tab }}">
                        <div class="card-hint" style="margin-bottom:8px;">Mitra ini belum terdaftar di LMS {{ $platformLabel }} (belum masuk tabel di bawah). Otomatis terdaftar begitu ada video yang dicentang, atau daftarkan dulu sekarang.</div>
                        <button type="submit" class="btn" style="width:auto;">Daftarkan ke LMS {{ $platformLabel }}</button>
                    </form>
                @endunless

                <div>
                    @foreach ($steps as $step)
                        @php $c = $selectedCompletions->get($step->id); @endphp
                        <div style="padding:11px 0; border-top:1px solid var(--line);">
                            <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                                <span class="chip {{ $c ? 'chip-good' : '' }}" style="min-width:28px; text-align:center;">{{ $step->urutan }}</span>
                                <div style="flex:1; min-width:180px; font-weight:600; font-size:13.5px;">{{ $step->judul }}</div>
                                @if ($c)
                                    <a href="{{ $c->link_gdrive }}" target="_blank" rel="noopener" style="font-size:12.5px; color:var(--accent-ink); font-weight:600; text-decoration:none;">&#10003; Lihat bukti</a>
                                    <button type="button" class="btn" style="width:auto; font-size:12px; padding:5px 10px;" onclick="toggleLmsForm({{ $step->id }})">Ubah link</button>
                                    <form method="POST" action="{{ route('growth-specialist.set-up-lms.step.destroy') }}" onsubmit="return confirm('Batalkan centang video {{ $step->urutan }}? Link GDrive-nya ikut terhapus.');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="mitra_id" value="{{ $selectedMitra->id }}">
                                        <input type="hidden" name="lms_step_id" value="{{ $step->id }}">
                                        <button type="submit" class="btn btn-danger" style="width:auto; font-size:12px; padding:5px 10px;">Batalkan</button>
                                    </form>
                                @else
                                    <button type="button" class="btn btn-primary" style="width:auto; font-size:12.5px; padding:6px 14px;" onclick="toggleLmsForm({{ $step->id }})">&#10003; Centang</button>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('growth-specialist.set-up-lms.step.store') }}" id="lmsForm{{ $step->id }}" style="display:none; margin-top:10px; gap:8px; align-items:flex-end; flex-wrap:wrap;">
                                @csrf
                                <input type="hidden" name="mitra_id" value="{{ $selectedMitra->id }}">
                                <input type="hidden" name="lms_step_id" value="{{ $step->id }}">
                                <div class="field" style="margin-bottom:0; flex:1; min-width:240px;">
                                    <label>Link GDrive bukti (wajib)</label>
                                    <input type="url" name="link_gdrive" value="{{ $c->link_gdrive ?? '' }}" placeholder="https://drive.google.com/..." required>
                                </div>
                                <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                                <button type="button" class="btn" style="width:auto;" onclick="toggleLmsForm({{ $step->id }})">Batal</button>
                            </form>
                        </div>
                    @endforeach
                </div>
                <script>
                    function toggleLmsForm(id) {
                        const f = document.getElementById('lmsForm' + id);
                        f.style.display = f.style.display === 'none' ? 'flex' : 'none';
                        if (f.style.display === 'flex') f.querySelector('input[name=link_gdrive]').focus();
                    }
                </script>
            @endif
        </section>

        <section class="card table-card" style="padding:0;">
            <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
                <div>
                    <div class="card-title">Progres LMS {{ $platformLabel }}</div>
                    <div class="card-hint">{{ $rowsPage->total() }} mitra{{ $q !== '' || $status !== '' ? ' (hasil filter)' : '' }}</div>
                </div>
                <form method="GET" action="{{ route('growth-specialist.set-up-lms') }}" class="field-row" style="margin-bottom:0; align-items:flex-end;">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="field" style="margin-bottom:0;">
                        <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama atau kode mitra...">
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <select name="status" class="select-pill" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            @foreach (['Lengkap', 'Proses', 'Awal'] as $s)
                                <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn" style="width:auto;">Cari</button>
                    @if ($q !== '' || $status !== '')
                        <a href="{{ route('growth-specialist.set-up-lms', ['tab' => $tab]) }}" class="btn" style="width:auto;">Reset</a>
                    @endif
                </form>
            </div>
            <div class="table-scroll" style="max-height:none; overflow-y:visible;">
                <table>
                    <thead>
                        <tr>
                            <th>ID Mitra</th><th>Nama Mitra</th><th>KAE</th><th>Segmen</th>
                            @foreach ($steps as $step)
                                <th style="min-width:86px; font-size:10.5px; white-space:normal; text-align:center;" title="{{ $step->judul }}">{{ $step->urutan }}. {{ $step->judul }}</th>
                            @endforeach
                            <th>% Selesai</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rowsPage as $r)
                            <tr>
                                <td style="font-size:12px;">{{ $r->mitra->kode_mitra }}</td>
                                <td><a href="{{ route('growth-specialist.set-up-lms', ['tab' => $tab, 'mitra_id' => $r->mitra->id]) }}#input" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $r->mitra->nama }}</a></td>
                                <td>{{ $r->kae ?? '—' }}</td>
                                <td>{{ $r->segmen ?? '—' }}</td>
                                @foreach ($steps as $step)
                                    @php $c = $r->completions->get($step->id); @endphp
                                    <td style="text-align:center;">
                                        @if ($c)
                                            <a href="{{ $c->link_gdrive }}" target="_blank" rel="noopener" title="Buka bukti GDrive" style="color:var(--good); font-weight:700; text-decoration:none;">&#10003;</a>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="tnum" style="font-weight:700;">{{ $r->pct }}%</td>
                                <td><span class="chip chip-{{ $statusColor[$r->status] }}">{{ $r->status }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('growth-specialist.set-up-lms.enroll.destroy') }}" onsubmit="return confirm('Hapus {{ addslashes($r->mitra->nama) }} dari LMS {{ $platformLabel }}? Semua centang dan link GDrive-nya ikut terhapus.');">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="mitra_id" value="{{ $r->mitra->id }}">
                                        <input type="hidden" name="platform" value="{{ $tab }}">
                                        <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ 7 + $steps->count() }}" style="color:var(--ink-muted);">{{ $q !== '' ? 'Tidak ada mitra yang cocok.' : 'Belum ada mitra yang terdaftar di LMS '.$platformLabel.'. Pilih mitra di atas untuk mulai.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <div style="margin-top:16px;">{{ $rowsPage->links() }}</div>
    @endif
@endsection
