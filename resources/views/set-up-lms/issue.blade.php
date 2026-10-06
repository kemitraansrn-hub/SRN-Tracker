@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px; margin-bottom:4px;">Issue</h1>
    <div class="card-hint" style="margin-bottom:16px;">Catat kendala mitra selama mengerjakan LMS (device, tidak respon, waktu, dll) per platform. Mitra yang bisa dipilih cuma yang sudah terdaftar di Set Up LMS.</div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('growth-specialist.set-up-lms.issue.store') }}" class="card" style="max-width:760px; margin-bottom:24px;">
        @csrf

        <div class="field">
            <label>Nama Mitra</label>
            @include('partials.searchable-select', [
                'name' => 'mitra_id',
                'options' => $mitraOptions,
                'selectedId' => old('mitra_id'),
                'placeholder' => 'Ketik buat cari mitra yang sudah terdaftar di Set Up LMS...',
            ])
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Platform</label>
                <select name="platform" required>
                    <option value="">— pilih —</option>
                    @foreach ($platforms as $key => $label)
                        <option value="{{ $key }}" {{ old('platform') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Detail</label>
                <select name="detail" required>
                    <option value="">— pilih —</option>
                    @foreach ($detailOptions as $d)
                        <option value="{{ $d }}" {{ old('detail') === $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label>Isu &amp; Kendala</label>
            <textarea name="isu_kendala" rows="4" required>{{ old('isu_kendala') }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan Issue</button>
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
            <div>
                <div class="card-title">Daftar Issue</div>
                <div class="card-hint">{{ $issues->total() }} issue{{ $q !== '' || $platformFilter !== '' ? ' (hasil filter)' : '' }}</div>
            </div>
            <form method="GET" action="{{ route('growth-specialist.set-up-lms.issue') }}" class="field-row" style="margin-bottom:0; align-items:flex-end;">
                <div class="field" style="margin-bottom:0;">
                    <input type="text" name="q" value="{{ $q }}" placeholder="Cari nama atau kode mitra...">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <select name="platform" class="select-pill" onchange="this.form.submit()">
                        <option value="">Semua Platform</option>
                        @foreach ($platforms as $key => $label)
                            <option value="{{ $key }}" {{ $platformFilter === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn" style="width:auto;">Cari</button>
                @if ($q !== '' || $platformFilter !== '')
                    <a href="{{ route('growth-specialist.set-up-lms.issue') }}" class="btn" style="width:auto;">Reset</a>
                @endif
            </form>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Nama Mitra</th><th>Platform</th><th>Detail</th><th>Isu &amp; Kendala</th><th>Dicatat Oleh</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($issues as $issue)
                        <tr>
                            <td style="white-space:nowrap;">{{ $issue->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $issue->mitra->nama }}</div>
                                <div style="font-size:11px; color:var(--ink-muted);">{{ $issue->mitra->kode_mitra }}</div>
                            </td>
                            <td>{{ $platforms[$issue->platform] ?? $issue->platform }}</td>
                            <td>{{ $issue->detail }}</td>
                            <td style="max-width:320px; white-space:normal;">{{ $issue->isu_kendala }}</td>
                            <td>{{ $issue->creator->name ?? '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('growth-specialist.set-up-lms.issue.destroy', $issue) }}" onsubmit="return confirm('Hapus issue ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">Belum ada issue yang dicatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $issues->links() }}</div>
@endsection
