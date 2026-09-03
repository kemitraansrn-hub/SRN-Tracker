@extends('layouts.app')

@php
    $rp = fn ($v) => $v === null ? '—' : 'Rp'.number_format((float) $v, 0, ',', '.');
    $clean = fn ($v) => $v === null ? '' : (int) $v;
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Input Reward</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">{{ $rewardList->count() }} reward tahun {{ $tahun }}</div>
        </div>
        <div style="display:flex; gap:10px; align-items:flex-end;">
            <form method="GET" action="{{ route('reward.index') }}" class="field" style="margin-bottom:0;">
                <label>Tahun</label>
                <select name="tahun" class="select-pill" onchange="this.form.submit()">
                    @for ($y = now()->year + 1; $y >= now()->year - 4; $y--)
                        <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>
            <button type="button" class="btn btn-primary" style="width:auto;" onclick="const d = document.getElementById('tambah-reward'); d.style.display = d.style.display === 'none' ? '' : 'none';">+ Tambah Reward</button>
        </div>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <section id="tambah-reward" class="card" style="margin-bottom:20px; display:none;">
        <div class="info-label" style="margin-bottom:10px;">Tambah Reward Tahun {{ $tahun }}</div>
        <form method="POST" action="{{ route('reward.store') }}" class="field-row" style="align-items:flex-end;">
            @csrf
            <input type="hidden" name="tahun" value="{{ $tahun }}">
            <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
                <label>Nama Reward</label>
                <input type="text" name="nama" required placeholder="mis. Cashback">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Poin Dibutuhkan</label>
                <input type="number" name="poin_dibutuhkan" min="1" required placeholder="mis. 500">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Harga Reward (Rp)</label>
                <input type="number" name="harga_reward" min="0" required placeholder="mis. 200000">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Budget Reward (Rp)</label>
                <input type="number" name="budget_reward" min="0" placeholder="opsional, cuma info">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Status</label>
                <select name="status">
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
        </form>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Nama Reward</th><th>Poin Dibutuhkan</th><th>Harga Reward</th><th>Budget Reward</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($rewardList as $r)
                        <tr>
                            <td style="font-weight:600;">{{ $r->nama }}</td>
                            <td class="tnum">{{ $r->poin_dibutuhkan }}</td>
                            <td class="tnum">{{ $rp($r->harga_reward) }}</td>
                            <td class="tnum">{{ $rp($r->budget_reward) }}</td>
                            <td>
                                @if ($r->status === 'aktif')
                                    <span class="chip chip-good">Aktif</span>
                                @else
                                    <span class="chip chip-neutral">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="const d = document.getElementById('edit-reward-{{ $r->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">Edit</button>
                                    <form method="POST" action="{{ route('reward.destroy', $r) }}" onsubmit="return confirm('Hapus reward {{ $r->nama }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="edit-reward-{{ $r->id }}" style="display:none;">
                            <td colspan="6" style="background:var(--surface-alt);">
                                <form method="POST" action="{{ route('reward.update', $r) }}" class="field-row" style="align-items:flex-end; padding:10px 4px;">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="tahun" value="{{ $r->tahun }}">
                                    <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
                                        <label>Nama Reward</label>
                                        <input type="text" name="nama" value="{{ $r->nama }}" required>
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Poin Dibutuhkan</label>
                                        <input type="number" name="poin_dibutuhkan" min="1" value="{{ $r->poin_dibutuhkan }}" required>
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Harga Reward (Rp)</label>
                                        <input type="number" name="harga_reward" min="0" value="{{ $clean($r->harga_reward) }}" required>
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Budget Reward (Rp)</label>
                                        <input type="number" name="budget_reward" min="0" value="{{ $clean($r->budget_reward) }}">
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Status</label>
                                        <select name="status">
                                            <option value="aktif" {{ $r->status === 'aktif' ? 'selected' : '' }}>Aktif</option>
                                            <option value="nonaktif" {{ $r->status === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:var(--ink-muted);">Belum ada reward untuk tahun {{ $tahun }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
