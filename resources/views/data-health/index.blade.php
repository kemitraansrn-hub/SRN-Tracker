@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <h1 class="display" style="font-size:24px;">Cek Kesehatan Data</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Kumpulan query pengecekan otomatis untuk nangkep data bermasalah (tanggal kosong, kode salah format, dsb) sebelum bikin laporan meleset &mdash; jalankan halaman ini tiap habis import data. Semua pengecekan di sini hanya membaca data, tidak mengubah apapun.
    </div>

    <div style="display:flex; flex-direction:column; gap:14px;">
        @foreach ($checks as $c)
            <section class="card" style="padding:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; padding:16px 20px; {{ $c['count'] > 0 ? 'cursor:pointer;' : '' }}"
                    @if ($c['count'] > 0) onclick="const d = document.getElementById('detail-{{ $c['key'] }}'); d.style.display = d.style.display === 'none' ? '' : 'none';" @endif>
                    <div>
                        <div style="font-weight:700; font-size:14.5px; display:flex; align-items:center; gap:8px;">
                            {{ $c['label'] }}
                            @if ($c['count'] > 0)
                                <span class="chip chip-critical">{{ $c['count'] }} ditemukan</span>
                            @else
                                <span class="chip chip-good">Aman</span>
                            @endif
                        </div>
                        <div style="color:var(--ink-muted); font-size:12.5px; margin-top:4px; max-width:760px;">{{ $c['description'] }}</div>
                    </div>
                    @if ($c['count'] > 0)
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--ink-faint); flex:none;"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    @endif
                </div>

                @if ($c['count'] > 0)
                    <div id="detail-{{ $c['key'] }}" style="display:none; border-top:1px solid var(--line);">
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        @foreach (array_keys((array) $c['rows']->first()) as $col)
                                            <th>{{ $col }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($c['rows'] as $row)
                                        <tr>
                                            @foreach ((array) $row as $val)
                                                <td class="tnum" style="font-size:12px;">{{ $val === null || $val === '' ? '—' : $val }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($c['count'] > $c['rows']->count())
                            <div style="padding:10px 20px; font-size:11.5px; color:var(--ink-faint);">
                                Menampilkan {{ $c['rows']->count() }} dari {{ $c['count'] }} baris.
                            </div>
                        @endif
                    </div>
                @endif
            </section>
        @endforeach
    </div>
@endsection
