@extends('layouts.app')

@php
    $isEdit = isset($priceAdjustmentRequest);
    $existingItemsJson = $isEdit ? $priceAdjustmentRequest->items->map(fn ($i) => [
        'produk_id' => $i->produk_id,
        'harga_het' => (float) $i->harga_het,
        'harga_diskon' => (float) $i->harga_diskon,
        'link_etalase' => $i->link_etalase,
    ])->values() : collect();
@endphp

@section('content')
    <a href="{{ route('price-adjustment.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">{{ $isEdit ? 'Koreksi Pengajuan' : 'Ajukan Izin Penyesuaian Harga' }}</h1>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('price-adjustment.update', $priceAdjustmentRequest) : route('price-adjustment.store') }}" class="card" style="max-width:820px;">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="field">
            <label>Mitra</label>
            <select name="mitra_id" required>
                <option value="">— pilih —</option>
                @foreach ($mitraOptions as $m)
                    <option value="{{ $m->id }}" {{ (string) old('mitra_id', $isEdit ? $priceAdjustmentRequest->mitra_id : '') === (string) $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                @endforeach
            </select>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Nama Toko</label>
                <input type="text" name="toko" value="{{ old('toko', $isEdit ? $priceAdjustmentRequest->toko : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Marketplace</label>
                <select name="marketplace" required>
                    <option value="">— pilih —</option>
                    @foreach ($marketplaceOptions as $mp)
                        <option value="{{ $mp }}" {{ old('marketplace', $isEdit ? $priceAdjustmentRequest->marketplace : '') === $mp ? 'selected' : '' }}>{{ $mp }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label>Link Toko (opsional)</label>
            <input type="text" name="link_toko" value="{{ old('link_toko', $isEdit ? $priceAdjustmentRequest->link_toko : '') }}" placeholder="https://... (link etalase toko secara umum)">
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $isEdit ? $priceAdjustmentRequest->tanggal_mulai->toDateString() : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $isEdit ? $priceAdjustmentRequest->tanggal_selesai->toDateString() : '') }}" required>
            </div>
        </div>

        <div class="field" style="margin-bottom:10px;">
            <label>SKU yang Diajukan Turun Harga</label>
            <div style="font-size:11px; color:var(--ink-muted); margin-bottom:10px;">Tiap SKU punya link etalase Shopee-nya sendiri — kalau ada lebih dari 1 produk yang mau didiskon, tambahkan barisnya satu-satu.</div>
            <div id="skuItemsContainer"></div>
            <button type="button" class="btn" style="width:auto; font-size:12.5px;" onclick="tambahSkuRow()">+ Tambah SKU</button>
        </div>

        <div class="field">
            <label>Catatan (opsional)</label>
            <textarea name="catatan" rows="3" placeholder="Alasan mitra minta izin turun harga...">{{ old('catatan', $isEdit ? $priceAdjustmentRequest->catatan : '') }}</textarea>
        </div>

        @if ($isEdit)
            <div class="field">
                <label>Status</label>
                <div style="padding:10px 13px; border:1px solid var(--line); border-radius:10px; background:var(--surface-alt); color:var(--ink-muted); font-size:14px;">
                    {{ $priceAdjustmentRequest->status_approval }}
                </div>
                <div style="font-size:11px; color:var(--ink-muted); margin-top:4px;">Keputusan Approve/Reject dilakukan lewat tombol "Putuskan" di daftar, bukan di sini.</div>
            </div>
        @endif

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Pengajuan' }}</button>
    </form>

    <template id="skuRowTemplate">
        <div class="sku-item-row" style="border:1px solid var(--line); border-radius:10px; padding:14px; margin-bottom:10px;">
            <div class="field-row">
                <div class="field" style="flex:2; margin-bottom:12px;">
                    <label>Produk</label>
                    <select name="items[__INDEX__][produk_id]" class="sku-produk-select" onchange="isiHetItem(this)" required>
                        <option value="">— pilih dari Master Produk —</option>
                        @foreach ($produkOptions as $p)
                            <option value="{{ $p->id }}" data-het="{{ $p->harga_het }}">{{ \Illuminate\Support\Str::limit($p->nama, 45) }} ({{ $p->brand }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="flex:1; margin-bottom:12px;">
                    <label>HET (Rp)</label>
                    <input type="number" step="1" min="0" name="items[__INDEX__][harga_het]" class="sku-het-input" oninput="hitungDiskonItem(this)" required>
                    <div style="font-size:10.5px; color:var(--ink-muted); margin-top:3px;">Auto dari Master Produk, bisa dikoreksi manual</div>
                </div>
            </div>
            <div class="field-row">
                <div class="field" style="flex:1; margin-bottom:12px;">
                    <label>Harga Diskon (Rp)</label>
                    <input type="number" step="1" min="0" name="items[__INDEX__][harga_diskon]" class="sku-diskon-input" oninput="hitungDiskonItem(this)" required>
                </div>
                <div class="field" style="flex:1; margin-bottom:12px;">
                    <label>% Diskon</label>
                    <div class="sku-pct-display tnum" style="padding:10px 13px; border:1px solid var(--line); border-radius:10px; background:var(--surface-alt); color:var(--ink-muted); font-size:14px; font-weight:700;">—</div>
                </div>
            </div>
            <div class="field" style="margin-bottom:10px;">
                <label>Link Etalase Shopee (produk ini)</label>
                <input type="text" name="items[__INDEX__][link_etalase]" class="sku-link-input" placeholder="https://shopee.co.id/..." required>
            </div>
            <button type="button" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="hapusSkuRow(this)">Hapus SKU ini</button>
        </div>
    </template>

    <script>
        let skuRowIndex = 0;

        function tambahSkuRow(prefill) {
            const template = document.getElementById('skuRowTemplate');
            const clone = template.content.cloneNode(true);
            const idx = skuRowIndex++;
            clone.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace('__INDEX__', idx);
            });
            document.getElementById('skuItemsContainer').appendChild(clone);

            if (prefill) {
                const rows = document.querySelectorAll('#skuItemsContainer .sku-item-row');
                const row = rows[rows.length - 1];
                row.querySelector('.sku-produk-select').value = prefill.produk_id ?? '';
                row.querySelector('.sku-het-input').value = prefill.harga_het ?? '';
                row.querySelector('.sku-diskon-input').value = prefill.harga_diskon ?? '';
                row.querySelector('.sku-link-input').value = prefill.link_etalase ?? '';
                hitungDiskonItem(row.querySelector('.sku-diskon-input'));
            }
        }

        function hapusSkuRow(btn) {
            const rows = document.querySelectorAll('#skuItemsContainer .sku-item-row');
            if (rows.length <= 1) {
                alert('Minimal harus ada 1 SKU yang diajukan.');
                return;
            }
            btn.closest('.sku-item-row').remove();
        }

        function isiHetItem(select) {
            const opt = select.options[select.selectedIndex];
            const het = opt ? opt.getAttribute('data-het') : null;
            const row = select.closest('.sku-item-row');
            if (het && het !== '' && het !== 'null') {
                row.querySelector('.sku-het-input').value = Math.round(parseFloat(het));
            }
            hitungDiskonItem(row.querySelector('.sku-diskon-input'));
        }

        function hitungDiskonItem(el) {
            const row = el.closest('.sku-item-row');
            const het = parseFloat(row.querySelector('.sku-het-input').value) || 0;
            const diskonInput = row.querySelector('.sku-diskon-input').value;
            const diskon = parseFloat(diskonInput);
            const display = row.querySelector('.sku-pct-display');
            if (het > 0 && diskonInput !== '' && !isNaN(diskon)) {
                display.textContent = ((het - diskon) / het * 100).toFixed(2) + '%';
            } else {
                display.textContent = '—';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const oldItems = @json(old('items'));
            const existingItems = @json($existingItemsJson);

            const itemsToLoad = (oldItems && Object.keys(oldItems).length > 0) ? Object.values(oldItems) : existingItems;

            if (itemsToLoad.length > 0) {
                itemsToLoad.forEach(function (item) { tambahSkuRow(item); });
            } else {
                tambahSkuRow();
            }
        });
    </script>
@endsection
