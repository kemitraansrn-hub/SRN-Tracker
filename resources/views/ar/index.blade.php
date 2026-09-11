@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <h1 class="display" style="font-size:24px;">Data Piutang / AR</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Catatan piutang per order &mdash; jumlah AR &amp; jatuh tempo diinput manual, bisa dibayar dicicil sampai lunas.
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <section style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:20px;">
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Total AR</div>
            <div style="font-size:25px; font-weight:700;" class="tnum">{{ $rp($stats['total_sisa']) }}</div>
            <div style="margin-top:6px; font-size:12px; color:var(--ink-muted);">{{ $stats['total_count'] }} AR</div>
        </div>
        <div class="card" style="background:var(--critical-soft); border-color:transparent;">
            <div class="info-label" style="margin-bottom:10px; color:var(--critical);">AR Jatuh Tempo</div>
            <div style="font-size:25px; font-weight:700; color:var(--critical);" class="tnum">{{ $rp($stats['jatuh_tempo_sisa']) }}</div>
            <div style="margin-top:6px; font-size:12px; color:var(--critical);">
                {{ $stats['jatuh_tempo_count'] }} AR
                @if ($stats['jatuh_tempo_pct'] !== null)
                    &middot; {{ $stats['jatuh_tempo_pct'] }}% dari Total AR
                @endif
            </div>
        </div>
        <div class="card" style="background:var(--good-soft); border-color:transparent;">
            <div class="info-label" style="margin-bottom:10px; color:var(--good);">AR Belum Jatuh Tempo</div>
            <div style="font-size:25px; font-weight:700; color:var(--good);" class="tnum">{{ $rp($stats['belum_jatuh_tempo_sisa']) }}</div>
            <div style="margin-top:6px; font-size:12px; color:var(--good);">
                {{ $stats['belum_jatuh_tempo_count'] }} AR
                @if ($stats['belum_jatuh_tempo_pct'] !== null)
                    &middot; {{ $stats['belum_jatuh_tempo_pct'] }}% dari Total AR
                @endif
            </div>
        </div>
    </section>

    @if ($stats['jatuh_tempo_count'] > 0)
        <section class="card" style="margin-bottom:20px;">
            <div class="info-label" style="margin-bottom:12px;">Umur Aging AR Jatuh Tempo (dari Total AR Jatuh Tempo: {{ $rp($stats['jatuh_tempo_sisa']) }})</div>
            <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:16px;">
                @foreach ($agingBuckets as $b)
                    @php
                        $bucketColor = match ($b['bucket']) {
                            '1-14' => 'warn',
                            '15-30' => 'critical',
                            default => 'critical',
                        };
                        $bucketBg = $b['bucket'] === '1-14' ? 'var(--warn-soft)' : 'var(--critical-soft)';
                        $bucketInk = $b['bucket'] === '1-14' ? 'var(--warn)' : 'var(--critical)';
                        $emphasis = match ($b['bucket']) {
                            '31-60' => 'box-shadow:inset 0 0 0 1px var(--critical);',
                            '61+' => 'box-shadow:inset 0 0 0 2px var(--critical);',
                            default => '',
                        };
                    @endphp
                    <div style="background:{{ $bucketBg }}; border-radius:10px; padding:12px 14px; {{ $emphasis }}">
                        <div style="font-size:11px; font-weight:700; letter-spacing:0.03em; color:{{ $bucketInk }};">{{ $b['bucket'] }} HARI</div>
                        <div style="font-size:19px; font-weight:700; margin-top:6px; color:{{ $bucketInk }};" class="tnum">{{ $rp($b['sisa']) }}</div>
                        <div style="margin-top:4px; font-size:11.5px; color:{{ $bucketInk }};">
                            {{ $b['count'] }} AR
                            @if ($b['pct'] !== null)
                                &middot; {{ $b['pct'] }}%
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if (auth()->user()->canViewAll())
    <section class="card" style="margin-bottom:20px;">
            <div class="info-label" style="margin-bottom:10px;">Cari Order untuk Input AR Baru</div>
            <form method="GET" action="{{ route('ar.index') }}" class="field-row" style="align-items:flex-end;">
                <div class="field" style="margin-bottom:0; flex:1; min-width:160px;">
                    <label>No Order</label>
                    <input type="text" name="cari_no_order" value="{{ request('cari_no_order') }}" placeholder="mis. 14226">
                </div>
                <div class="field" style="margin-bottom:0; flex:1; min-width:160px;">
                    <label>Nama Mitra</label>
                    <input type="text" name="cari_mitra" value="{{ request('cari_mitra') }}" placeholder="Nama mitra...">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Tgl Order Dari</label>
                    <input type="date" name="cari_dari" value="{{ request('cari_dari') }}">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Tgl Order Sampai</label>
                    <input type="date" name="cari_sampai" value="{{ request('cari_sampai') }}">
                </div>
                <button type="submit" class="btn" style="width:auto;">Cari</button>
                @if (request('cari_no_order') || request('cari_mitra') || request('cari_dari') || request('cari_sampai'))
                    <a href="{{ route('ar.index') }}" class="btn" style="width:auto;">Reset</a>
                @endif
            </form>

            @if (request('cari_no_order') || request('cari_mitra') || request('cari_dari') || request('cari_sampai'))
                <div class="table-scroll" style="margin-top:16px;">
                    <table>
                        <thead>
                            <tr><th>No Order</th><th>Tgl Order</th><th>Mitra</th><th>Total Order</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($orderResults as $o)
                                <tr>
                                    <td class="tnum">{{ $o->no_order }}</td>
                                    <td class="tnum">{{ $o->tanggal_order->format('d/m/Y') }}</td>
                                    <td>{{ $o->mitra->nama ?? '—' }}</td>
                                    <td class="tnum">{{ $rp($o->total_transaksi) }}</td>
                                    <td>
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="const d = document.getElementById('input-ar-{{ $o->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">Input AR</button>
                                    </td>
                                </tr>
                                <tr id="input-ar-{{ $o->id }}" style="display:none;">
                                    <td colspan="5" style="background:var(--surface-alt);">
                                        <form method="POST" action="{{ route('ar.store') }}" class="field-row" style="align-items:flex-end; padding:10px 4px;">
                                            @csrf
                                            <input type="hidden" name="order_id" value="{{ $o->id }}">
                                            <div class="field" style="margin-bottom:0;">
                                                <label>Jumlah AR (Rp)</label>
                                                <input type="number" name="jumlah_ar" min="1" placeholder="mis. {{ (int) $o->total_transaksi }}" required>
                                            </div>
                                            <div class="field" style="margin-bottom:0;">
                                                <label>Jatuh Tempo</label>
                                                <select name="jatuh_tempo_hari">
                                                    <option value="14">14 hari</option>
                                                    <option value="30">30 hari</option>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" style="color:var(--ink-muted);">Tidak ada order yang cocok (atau semuanya sudah punya AR).</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
    </section>
    @endif

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px; display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap;">
            <div>
                <div class="card-title">Daftar AR</div>
                <div class="card-hint">{{ $arList->count() }} AR</div>
            </div>
            <a href="{{ route('ar.export', ['status' => request('status')]) }}" class="btn" style="width:auto; text-decoration:none;">Download Excel</a>
        </div>

        <form method="GET" action="{{ route('ar.index') }}" class="field-row" style="padding:0 20px 16px; margin-bottom:0;">
            <div class="field" style="margin-bottom:0;">
                <label>Status</label>
                <select name="status" class="select-pill" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    <option value="belum-lunas" {{ request('status') === 'belum-lunas' ? 'selected' : '' }}>Belum Lunas</option>
                    <option value="lunas" {{ request('status') === 'lunas' ? 'selected' : '' }}>Lunas</option>
                    <option value="jatuh-tempo" {{ request('status') === 'jatuh-tempo' ? 'selected' : '' }}>Jatuh Tempo</option>
                </select>
            </div>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th><th>No Order</th><th>Tgl Order</th><th>Total Order</th><th>Jumlah AR</th><th>Sisa AR</th>
                        <th>Jatuh Tempo</th><th>Umur</th><th>Status</th><th>Cicilan</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($arList as $ar)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $ar->order->mitra->nama ?? '—' }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $ar->order->mitra->kode_mitra ?? '' }}</div>
                            </td>
                            <td class="tnum">{{ $ar->order->no_order }}</td>
                            <td class="tnum">{{ $ar->order->tanggal_order->format('d/m/Y') }}</td>
                            <td class="tnum">{{ $rp($ar->order->total_transaksi) }}</td>
                            <td class="tnum">{{ $rp($ar->jumlah_ar) }}</td>
                            <td class="tnum" style="font-weight:700;">{{ $rp($ar->sisa_ar) }}</td>
                            <td class="tnum">{{ $ar->tanggal_jatuh_tempo->format('d/m/Y') }}</td>
                            <td class="tnum">
                                @if ($ar->hari_terlambat !== null)
                                    <span class="chip chip-critical">{{ $ar->hari_terlambat }} hari</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($ar->jatuh_tempo_flag)
                                    <span class="chip chip-critical">Jatuh Tempo</span>
                                @elseif ($ar->lunas)
                                    <span class="chip chip-good">Lunas</span>
                                @else
                                    <span class="chip chip-warn">Belum Lunas</span>
                                @endif
                            </td>
                            <td>
                                @if ($ar->payments->isEmpty())
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @else
                                    <button type="button" class="btn" style="width:auto; font-size:11px; padding:4px 8px;" onclick="const d = document.getElementById('cicilan-{{ $ar->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">{{ $ar->payments->count() }}x cicilan</button>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    @if (auth()->user()->hasAdminAccess())
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="const d = document.getElementById('edit-ar-{{ $ar->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">Edit AR</button>
                                    @endif
                                    @if (! $ar->lunas && (auth()->user()->hasAdminAccess() || auth()->user()->kae_code === ($ar->order->mitra->kae_code ?? null)))
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="const d = document.getElementById('bayar-ar-{{ $ar->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">Bayar AR</button>
                                    @endif
                                    @if (auth()->user()->hasAdminAccess())
                                        <form method="POST" action="{{ route('ar.destroy', $ar) }}" onsubmit="return confirm('Hapus seluruh data AR order {{ $ar->order->no_order }} beserta semua cicilannya? Data tidak bisa dikembalikan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus AR</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if (auth()->user()->hasAdminAccess())
                            <tr id="edit-ar-{{ $ar->id }}" style="display:none;">
                                <td colspan="11" style="background:var(--surface-alt);">
                                    <form method="POST" action="{{ route('ar.update', $ar) }}" class="field-row" style="align-items:flex-end; padding:10px 4px;">
                                        @csrf
                                        @method('PUT')
                                        <div class="field" style="margin-bottom:0;">
                                            <label>Jumlah AR (Rp)</label>
                                            <input type="number" name="jumlah_ar" min="{{ max(1, (int) $ar->totalDibayar()) }}" value="{{ (int) $ar->jumlah_ar }}" required>
                                            @if ($ar->totalDibayar() > 0)
                                                <div style="font-size:11px; color:var(--ink-muted); margin-top:4px;">Minimal {{ $rp($ar->totalDibayar()) }} (sudah dibayar segitu).</div>
                                            @endif
                                        </div>
                                        <div class="field" style="margin-bottom:0;">
                                            <label>Jatuh Tempo</label>
                                            <select name="jatuh_tempo_hari">
                                                <option value="14" {{ $ar->jatuh_tempo_hari == 14 ? 'selected' : '' }}>14 hari</option>
                                                <option value="30" {{ $ar->jatuh_tempo_hari == 30 ? 'selected' : '' }}>30 hari</option>
                                            </select>
                                            <div style="font-size:11px; color:var(--ink-muted); margin-top:4px;">Dihitung ulang dari tanggal input ({{ $ar->tanggal_input->format('d/m/Y') }}).</div>
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan Perubahan</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                        @if ($ar->payments->isNotEmpty())
                            <tr id="cicilan-{{ $ar->id }}" style="display:none;">
                                <td colspan="11" style="background:var(--surface-alt); padding:10px 20px;">
                                    <div class="info-label" style="margin-bottom:6px;">Riwayat Cicilan</div>
                                    @foreach ($ar->payments as $p)
                                        <div style="display:flex; align-items:center; gap:8px; font-size:12px; color:var(--ink-muted); margin-bottom:2px;">
                                            <span>Cicilan ke-{{ $p->cicilan_ke }}: {{ $rp($p->jumlah_bayar) }} &middot; {{ $p->tanggal_bayar->format('d/m/Y') }}</span>
                                            @if (auth()->user()->hasAdminAccess() || auth()->user()->kae_code === ($ar->order->mitra->kae_code ?? null))
                                                <form method="POST" action="{{ route('ar.payment.destroy', $p) }}" onsubmit="return confirm('Hapus cicilan ke-{{ $p->cicilan_ke }} ini? Data tidak bisa dikembalikan.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger" style="width:auto; font-size:10.5px; padding:2px 7px; border-radius:6px;">Hapus</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endif
                        @if (! $ar->lunas && (auth()->user()->hasAdminAccess() || auth()->user()->kae_code === ($ar->order->mitra->kae_code ?? null)))
                            <tr id="bayar-ar-{{ $ar->id }}" style="display:none;">
                                <td colspan="11" style="background:var(--surface-alt);">
                                    <form method="POST" action="{{ route('ar.pay', $ar) }}" class="field-row" style="align-items:flex-end; padding:10px 4px;">
                                        @csrf
                                        <div class="field" style="margin-bottom:0;">
                                            <label>Jumlah Bayar (maks. {{ $rp($ar->sisa_ar) }})</label>
                                            <input type="number" name="jumlah_bayar" min="1" max="{{ (int) $ar->sisa_ar }}" placeholder="mis. {{ (int) $ar->sisa_ar }}" required>
                                        </div>
                                        <div class="field" style="margin-bottom:0;">
                                            <label>Tgl Bayar</label>
                                            <input type="date" name="tanggal_bayar" value="{{ now()->toDateString() }}" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan Cicilan ke-{{ $ar->payments->count() + 1 }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="11" style="color:var(--ink-muted);">Belum ada data AR.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
