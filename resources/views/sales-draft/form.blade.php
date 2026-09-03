@extends('layouts.app')

@php
    $existingItems = $draft
        ? $draft->items->map(fn ($i) => [
            'produk_id' => $i->produk_id,
            'nama_produk' => $i->nama_produk,
            'harga' => (float) $i->harga,
            'qty' => $i->qty,
        ])->values()
        : collect();
@endphp

@section('content')
    <h1 class="display" style="font-size:24px;">{{ $draft ? 'Edit Input Penjualan' : 'Input Penjualan Baru' }}</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:20px;">
        Sekadar kalkulator total order &mdash; ini <strong>tidak</strong> tercatat sebagai data penjualan resmi.
    </div>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $draft ? route('sales-draft.update', $draft) : route('sales-draft.store') }}" id="draft-form">
        @csrf
        @if ($draft)
            @method('PUT')
        @endif

        <section class="card" style="margin-bottom:20px;">
            <div class="field-row" style="align-items:flex-end;">
                <div class="field" style="margin-bottom:0;">
                    <label>Tanggal Order</label>
                    <input type="date" name="tanggal_order" value="{{ old('tanggal_order', isset($draft) && $draft ? $draft->tanggal_order->toDateString() : now()->toDateString()) }}" required>
                </div>
                <div class="field" style="margin-bottom:0; flex:1; min-width:220px;">
                    <label>Pilih Mitra</label>
                    <select id="mitra-select" name="mitra_id" required>
                        <option value="">-- pilih mitra --</option>
                        @foreach ($mitraList as $m)
                            <option value="{{ $m->id }}" data-alamat="{{ $m->alamat }}" {{ (old('mitra_id', $draft->mitra_id ?? '')) == $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0; flex:1; min-width:220px;">
                    <label>Alamat</label>
                    <input type="text" id="mitra-alamat" readonly style="background:var(--surface-alt); color:var(--ink-muted);" value="{{ $draft->mitra->alamat ?? '' }}">
                </div>
            </div>
        </section>

        <section class="card table-card" style="margin-bottom:20px;">
            <div class="card-head" style="margin-bottom:14px;">
                <div class="card-title">Produk<span style="color:var(--critical);">*</span></div>
                <button type="button" class="btn btn-primary" style="width:auto;" onclick="openProdukModal()">+ Tambah Produk</button>
            </div>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>Produk</th><th>Harga Jual</th><th>Qty</th><th>Sub Total</th><th></th></tr>
                    </thead>
                    <tbody id="items-tbody"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align:right; font-weight:700;">Total Produk:</td>
                            <td class="tnum" style="font-weight:700;" id="items-total">Rp0</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div id="items-empty" style="color:var(--ink-muted); font-size:13px; padding:14px 4px;">Belum ada produk dipilih.</div>
        </section>

        <section class="card" style="margin-bottom:20px;">
            <div class="field-row" style="align-items:flex-end;">
                <div class="field" style="margin-bottom:0;">
                    <label>Diskon Promo (Rp)</label>
                    <input type="number" id="diskon_promo" name="diskon_promo" min="0" value="{{ old('diskon_promo', $draft->diskon_promo ?? 0) }}" oninput="recalc()">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Diskon Ongkir (Rp)</label>
                    <input type="number" id="diskon_ongkir" name="diskon_ongkir" min="0" value="{{ old('diskon_ongkir', $draft->diskon_ongkir ?? 0) }}" oninput="recalc()">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Ongkir (Rp)</label>
                    <input type="number" id="ongkir" name="ongkir" min="0" value="{{ old('ongkir', $draft->ongkir ?? 0) }}" oninput="recalc()">
                </div>
                <div class="field" style="margin-bottom:0; margin-left:auto; text-align:right;">
                    <label>Grand Total</label>
                    <div id="grand-total" class="tnum" style="font-size:22px; font-weight:700; color:var(--accent-ink);">Rp0</div>
                </div>
            </div>
        </section>

        <div id="hidden-items"></div>

        <div style="display:flex; gap:10px;">
            <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
            <a href="{{ route('sales-draft.index') }}" class="btn" style="width:auto;">Batal</a>
        </div>
    </form>

    <div id="produk-modal" style="display:none; position:fixed; inset:0; background:rgba(27,34,44,0.45); z-index:100; align-items:flex-start; justify-content:center; padding:40px 20px;">
        <div class="card" style="max-width:720px; width:100%; max-height:80vh; display:flex; flex-direction:column; padding:0;">
            <div style="display:flex; justify-content:space-between; align-items:center; padding:18px 20px; border-bottom:1px solid var(--line);">
                <div class="card-title" style="margin:0;">Pilih Produk</div>
                <button type="button" onclick="closeProdukModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--ink-muted); line-height:1;">&times;</button>
            </div>
            <div style="padding:14px 20px;">
                <input type="text" id="produk-search" placeholder="Cari produk..." oninput="renderProdukOptions()" style="width:100%;">
            </div>
            <div class="table-scroll" style="flex:1; overflow-y:auto; padding:0 20px;">
                <table>
                    <thead><tr><th>Nama</th><th>Brand</th><th>Harga Jual</th><th></th></tr></thead>
                    <tbody id="produk-options"></tbody>
                </table>
            </div>
            <div style="padding:14px 20px; border-top:1px solid var(--line); text-align:right;">
                <button type="button" class="btn btn-primary" style="width:auto;" onclick="closeProdukModal()">Selesai</button>
            </div>
        </div>
    </div>

    <script>
        const produkList = @json($produkList->values());
        const rp = (v) => 'Rp' + Math.round(v || 0).toLocaleString('id-ID');
        let items = @json($existingItems);

        document.getElementById('mitra-select').addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            document.getElementById('mitra-alamat').value = opt ? (opt.dataset.alamat || '') : '';
        });

        function openProdukModal() {
            document.getElementById('produk-search').value = '';
            renderProdukOptions();
            document.getElementById('produk-modal').style.display = 'flex';
        }
        function closeProdukModal() {
            document.getElementById('produk-modal').style.display = 'none';
        }

        function renderProdukOptions() {
            const q = document.getElementById('produk-search').value.toLowerCase();
            const tbody = document.getElementById('produk-options');
            tbody.innerHTML = '';
            produkList
                .filter(p => p.nama.toLowerCase().includes(q) || (p.kode_sku || '').toLowerCase().includes(q))
                .forEach(p => {
                    const checked = items.some(i => i.produk_id === p.id);
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${p.kode_sku ? '<span style="color:var(--ink-faint); font-size:11.5px;">' + p.kode_sku + '</span><br>' : ''}${p.nama}</td>
                        <td>${p.brand}</td>
                        <td class="tnum">${rp(p.harga)}</td>
                        <td><input type="checkbox" ${checked ? 'checked' : ''} onchange="toggleProduk(${p.id}, this.checked)"></td>
                    `;
                    tbody.appendChild(tr);
                });
        }

        function toggleProduk(produkId, checked) {
            const p = produkList.find(x => x.id === produkId);
            if (checked) {
                if (!items.some(i => i.produk_id === produkId)) {
                    items.push({produk_id: p.id, nama_produk: p.nama, harga: p.harga || 0, qty: 1});
                }
            } else {
                items = items.filter(i => i.produk_id !== produkId);
            }
            renderItems();
        }

        function removeItem(index) {
            items.splice(index, 1);
            renderItems();
            renderProdukOptions();
        }

        function renderItems() {
            const tbody = document.getElementById('items-tbody');
            tbody.innerHTML = '';
            document.getElementById('items-empty').style.display = items.length ? 'none' : '';

            items.forEach((item, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.nama_produk}</td>
                    <td><input type="number" min="0" value="${item.harga}" style="width:110px;" onchange="updateItem(${idx}, 'harga', this.value)"></td>
                    <td><input type="number" min="1" value="${item.qty}" style="width:70px;" onchange="updateItem(${idx}, 'qty', this.value)"></td>
                    <td class="tnum" style="font-weight:600;">${rp(item.harga * item.qty)}</td>
                    <td><button type="button" class="btn btn-danger" style="width:auto; font-size:11px; padding:4px 8px;" onclick="removeItem(${idx})">Hapus</button></td>
                `;
                tbody.appendChild(tr);
            });

            recalc();
        }

        function updateItem(idx, field, value) {
            items[idx][field] = parseFloat(value) || 0;
            renderItems();
        }

        function recalc() {
            const itemsTotal = items.reduce((sum, i) => sum + (i.harga * i.qty), 0);
            document.getElementById('items-total').textContent = rp(itemsTotal);

            const diskonPromo = parseFloat(document.getElementById('diskon_promo').value) || 0;
            const diskonOngkir = parseFloat(document.getElementById('diskon_ongkir').value) || 0;
            const ongkir = parseFloat(document.getElementById('ongkir').value) || 0;
            const grandTotal = itemsTotal - diskonPromo - diskonOngkir + ongkir;
            document.getElementById('grand-total').textContent = rp(grandTotal);

            const hidden = document.getElementById('hidden-items');
            hidden.innerHTML = items.map((item, idx) => `
                <input type="hidden" name="items[${idx}][produk_id]" value="${item.produk_id ?? ''}">
                <input type="hidden" name="items[${idx}][nama_produk]" value="${item.nama_produk}">
                <input type="hidden" name="items[${idx}][harga]" value="${item.harga}">
                <input type="hidden" name="items[${idx}][qty]" value="${item.qty}">
            `).join('');
        }

        document.getElementById('draft-form').addEventListener('submit', function (e) {
            if (items.length === 0) {
                e.preventDefault();
                alert('Pilih minimal satu produk.');
            }
        });

        renderItems();
    </script>
@endsection
