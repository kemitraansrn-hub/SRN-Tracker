@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">{{ $redemption ? 'Edit Penukaran Poin' : 'Penukaran Poin Baru' }}</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:20px;">
        Poin ditukar dari saldo tahun {{ $tahun }}.
        @unless ($redemption)
            Bisa pilih lebih dari satu reward sekaligus, selama poinnya cukup.
        @endunless
    </div>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <div style="display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap;">
    <form method="POST" action="{{ $redemption ? route('poin-redemption.update', $redemption) : route('poin-redemption.store') }}" id="redemption-form" class="card" style="flex:1 1 500px; max-width:640px;">
        @csrf
        @if ($redemption)
            @method('PUT')
        @endif

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Nama Mitra</label>
                <select id="mitra-select" name="mitra_id" required>
                    <option value="">-- pilih mitra --</option>
                    @foreach ($mitraList as $m)
                        <option value="{{ $m->id }}" data-saldo="{{ $m->saldo_poin }}" {{ (old('mitra_id', $redemption->mitra_id ?? '')) == $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Saldo Poin Tersedia</label>
                <input type="text" id="mitra-saldo" readonly style="background:var(--surface-alt); color:var(--ink-muted);">
            </div>
        </div>

        @if ($redemption)
            {{-- Edit: satu reward per pengajuan, sesuai record yang diedit. --}}
            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Reward</label>
                    <select id="reward-select" name="reward_catalog_id" required onchange="recalc()">
                        <option value="">-- pilih reward --</option>
                        @foreach ($rewardList as $r)
                            <option value="{{ $r->id }}" data-poin="{{ $r->poin_dibutuhkan }}" data-harga="{{ $r->harga_reward }}" data-budget="{{ $r->budget_reward }}" data-nama="{{ $r->nama }}" {{ (old('reward_catalog_id', $redemption->reward_catalog_id ?? '')) == $r->id ? 'selected' : '' }}>{{ \Illuminate\Support\Str::limit($r->nama, 40) }} ({{ $r->poin_dibutuhkan }} poin)</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="flex:1;">
                    <label>Qty</label>
                    <input type="number" id="qty-input" name="qty" min="1" value="{{ old('qty', $redemption->qty ?? 1) }}" oninput="recalc()" required>
                </div>
            </div>
        @else
            {{-- Create: checklist multi-reward, mirip pemilihan produk di Input Penjualan. --}}
            <div class="field">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <label style="margin-bottom:0;">Reward<span style="color:var(--critical);">*</span></label>
                    <button type="button" class="btn" style="width:auto; font-size:12px; padding:6px 10px;" onclick="openRewardModal()">+ Tambah Reward</button>
                </div>
                <div class="table-scroll" style="border:1px solid var(--line); border-radius:10px;">
                    <table>
                        <thead><tr><th>Reward</th><th>Poin/pcs</th><th>Qty</th><th>Subtotal Poin</th><th></th></tr></thead>
                        <tbody id="reward-items-tbody"></tbody>
                    </table>
                </div>
                <div id="reward-items-empty" style="color:var(--ink-muted); font-size:12.5px; padding:10px 2px;">Belum ada reward dipilih.</div>
            </div>
            <div id="hidden-reward-items"></div>
        @endif

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Keterangan</label>
                <select id="keterangan-select" name="keterangan" required onchange="recalc()">
                    <option value="sesuai-reward" {{ old('keterangan', $redemption->keterangan ?? 'sesuai-reward') === 'sesuai-reward' ? 'selected' : '' }}>Sesuai dengan Reward</option>
                    <option value="di-uangkan" {{ old('keterangan', $redemption->keterangan ?? '') === 'di-uangkan' ? 'selected' : '' }}>Di Uangkan</option>
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Poin Terpakai</label>
                <div id="poin-terpakai" class="tnum" style="font-size:20px; font-weight:700; color:var(--accent-ink);">0</div>
            </div>
        </div>

        <div id="note-preview" style="display:none; font-size:12.5px; color:var(--ink-muted); background:var(--surface-alt); border:1px solid var(--line); border-radius:8px; padding:10px 12px; margin-bottom:16px;"></div>

        <button type="submit" id="submit-btn" class="btn btn-primary" style="width:auto; margin-top:8px;">Ajukan Penukaran</button>
        <div id="saldo-warning" style="display:none; color:var(--critical); font-size:12px; margin-top:6px;">Poin mitra tidak cukup untuk reward &amp; qty ini.</div>
        <a href="{{ route('poin-redemption.index') }}" class="btn" style="width:auto; margin-top:8px;">Batal</a>
    </form>

    <div class="card" style="flex:1 1 280px; max-width:320px;">
        <div class="card-title" style="margin-bottom:14px;">Ringkasan</div>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <div>
                <div class="info-label">Mitra</div>
                <div id="ringkasan-mitra" style="font-weight:600;">&mdash;</div>
            </div>
            <div>
                <div class="info-label">Saldo Poin Saat Ini</div>
                <div id="ringkasan-saldo" class="tnum" style="font-weight:600;">&mdash;</div>
            </div>
            <div>
                <div class="info-label">Reward</div>
                <div id="ringkasan-reward" style="font-weight:600;">&mdash;</div>
            </div>
            <div style="border-top:1px solid var(--line); padding-top:12px;">
                <div class="info-label">Poin Terpakai</div>
                <div id="ringkasan-terpakai" class="tnum" style="font-size:20px; font-weight:700; color:var(--accent-ink);">0</div>
            </div>
            <div>
                <div class="info-label">Sisa Saldo Setelah Ditukar</div>
                <div id="ringkasan-sisa" class="tnum" style="font-size:18px; font-weight:700;">&mdash;</div>
            </div>
        </div>
    </div>
    </div>

    @unless ($redemption)
        <div id="reward-modal" style="display:none; position:fixed; inset:0; background:rgba(27,34,44,0.45); z-index:100; align-items:flex-start; justify-content:center; padding:40px 20px;">
            <div class="card" style="max-width:640px; width:100%; max-height:80vh; display:flex; flex-direction:column; padding:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; padding:18px 20px; border-bottom:1px solid var(--line);">
                    <div class="card-title" style="margin:0;">Pilih Reward</div>
                    <button type="button" onclick="closeRewardModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--ink-muted); line-height:1;">&times;</button>
                </div>
                <div style="padding:14px 20px;">
                    <input type="text" id="reward-search" placeholder="Cari reward..." oninput="renderRewardOptions()" style="width:100%;">
                </div>
                <div class="table-scroll" style="flex:1; overflow-y:auto; padding:0 20px;">
                    <table>
                        <thead><tr><th>Reward</th><th>Poin Dibutuhkan</th><th></th></tr></thead>
                        <tbody id="reward-options"></tbody>
                    </table>
                </div>
                <div style="padding:14px 20px; border-top:1px solid var(--line); text-align:right;">
                    <button type="button" class="btn btn-primary" style="width:auto;" onclick="closeRewardModal()">Selesai</button>
                </div>
            </div>
        </div>
    @endunless

    @php
        $rewardCatalogJs = $rewardList->map(fn ($r) => [
            'id' => $r->id,
            'nama' => $r->nama,
            'poin' => (int) $r->poin_dibutuhkan,
            'harga' => (float) $r->harga_reward,
            'budget' => $r->budget_reward !== null ? (float) $r->budget_reward : null,
        ])->values();
        $rewardItemsJs = $redemption ? [[
            'reward_catalog_id' => $redemption->reward_catalog_id,
            'nama' => $redemption->nama_reward,
            'poin' => $redemption->poin_per_unit,
            'qty' => $redemption->qty,
        ]] : [];
    @endphp
    <script>
        const isEdit = @json((bool) $redemption);
        const rewardCatalog = @json($rewardCatalogJs);
        let rewardItems = @json($rewardItemsJs);

        function updateSaldo() {
            const sel = document.getElementById('mitra-select');
            const opt = sel.options[sel.selectedIndex];
            const saldo = opt && opt.value ? parseInt(opt.dataset.saldo) || 0 : null;
            document.getElementById('mitra-saldo').value = saldo !== null ? (saldo + ' poin') : '';
            document.getElementById('ringkasan-mitra').textContent = opt && opt.value ? opt.textContent : '—';
            document.getElementById('ringkasan-saldo').textContent = saldo !== null ? saldo.toLocaleString('id-ID') + ' poin' : '—';
            recalc();
        }
        document.getElementById('mitra-select').addEventListener('change', updateSaldo);

        function rp(v) {
            return 'Rp' + Math.round(v || 0).toLocaleString('id-ID');
        }

        @unless ($redemption)
        function openRewardModal() {
            document.getElementById('reward-search').value = '';
            renderRewardOptions();
            document.getElementById('reward-modal').style.display = 'flex';
        }
        function closeRewardModal() {
            document.getElementById('reward-modal').style.display = 'none';
        }

        function renderRewardOptions() {
            const q = document.getElementById('reward-search').value.toLowerCase();
            const tbody = document.getElementById('reward-options');
            tbody.innerHTML = '';
            rewardCatalog
                .filter(r => r.nama.toLowerCase().includes(q))
                .forEach(r => {
                    const checked = rewardItems.some(i => i.reward_catalog_id === r.id);
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${r.nama}</td>
                        <td class="tnum">${r.poin.toLocaleString('id-ID')}</td>
                        <td><input type="checkbox" ${checked ? 'checked' : ''} onchange="toggleReward(${r.id}, this.checked)"></td>
                    `;
                    tbody.appendChild(tr);
                });
        }

        function toggleReward(id, checked) {
            const r = rewardCatalog.find(x => x.id === id);
            if (checked) {
                if (!rewardItems.some(i => i.reward_catalog_id === id)) {
                    rewardItems.push({reward_catalog_id: r.id, nama: r.nama, poin: r.poin, qty: 1});
                }
            } else {
                rewardItems = rewardItems.filter(i => i.reward_catalog_id !== id);
            }
            renderRewardItems();
        }

        function removeRewardItem(idx) {
            rewardItems.splice(idx, 1);
            renderRewardItems();
            renderRewardOptions();
        }

        function updateRewardQty(idx, value) {
            rewardItems[idx].qty = parseInt(value) || 0;
            renderRewardItems();
        }

        function renderRewardItems() {
            const tbody = document.getElementById('reward-items-tbody');
            tbody.innerHTML = '';
            document.getElementById('reward-items-empty').style.display = rewardItems.length ? 'none' : '';

            rewardItems.forEach((item, idx) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.nama}</td>
                    <td class="tnum">${item.poin.toLocaleString('id-ID')}</td>
                    <td><input type="number" min="1" value="${item.qty}" style="width:70px;" onchange="updateRewardQty(${idx}, this.value)"></td>
                    <td class="tnum" style="font-weight:600;">${(item.poin * item.qty).toLocaleString('id-ID')}</td>
                    <td><button type="button" class="btn btn-danger" style="width:auto; font-size:11px; padding:4px 8px;" onclick="removeRewardItem(${idx})">Hapus</button></td>
                `;
                tbody.appendChild(tr);
            });

            const hidden = document.getElementById('hidden-reward-items');
            hidden.innerHTML = rewardItems.map((item, idx) => `
                <input type="hidden" name="rewards[${idx}][reward_catalog_id]" value="${item.reward_catalog_id}">
                <input type="hidden" name="rewards[${idx}][qty]" value="${item.qty}">
            `).join('');

            recalc();
        }
        @endunless

        function recalc() {
            const mitraSel = document.getElementById('mitra-select');
            const mitraOpt = mitraSel.options[mitraSel.selectedIndex];
            const saldo = mitraOpt && mitraOpt.value ? parseInt(mitraOpt.dataset.saldo) || 0 : null;

            let terpakai = 0;
            let rewardLabel = '—';

            if (isEdit) {
                const rewardSel = document.getElementById('reward-select');
                const rewardOpt = rewardSel.options[rewardSel.selectedIndex];
                const poinPerUnit = rewardOpt && rewardOpt.value ? parseInt(rewardOpt.dataset.poin) : 0;
                const qty = parseInt(document.getElementById('qty-input').value) || 0;
                const harga = rewardOpt && rewardOpt.value ? parseFloat(rewardOpt.dataset.harga) || 0 : 0;
                terpakai = poinPerUnit * qty;
                rewardLabel = rewardOpt && rewardOpt.value
                    ? (rewardOpt.dataset.nama + ' &times;' + qty + ' &mdash; ' + rp(harga * qty))
                    : '—';
            } else {
                terpakai = rewardItems.reduce((sum, i) => sum + i.poin * i.qty, 0);
                if (rewardItems.length) {
                    const lines = rewardItems.map(i => {
                        const r = rewardCatalog.find(x => x.id === i.reward_catalog_id);
                        const nilai = (r ? r.harga : 0) * i.qty;
                        return i.nama + ' &times;' + i.qty + ' &mdash; ' + rp(nilai);
                    });
                    if (rewardItems.length > 1) {
                        const totalNilai = rewardItems.reduce((sum, i) => {
                            const r = rewardCatalog.find(x => x.id === i.reward_catalog_id);
                            return sum + (r ? r.harga : 0) * i.qty;
                        }, 0);
                        lines.push('<strong>Total Nilai: ' + rp(totalNilai) + '</strong>');
                    }
                    rewardLabel = lines.join('<br>');
                } else {
                    rewardLabel = '—';
                }
            }

            document.getElementById('poin-terpakai').textContent = terpakai.toLocaleString('id-ID');
            document.getElementById('ringkasan-terpakai').textContent = terpakai.toLocaleString('id-ID');
            document.getElementById('ringkasan-reward').innerHTML = rewardLabel;

            const sisaEl = document.getElementById('ringkasan-sisa');
            const submitBtn = document.getElementById('submit-btn');
            const warning = document.getElementById('saldo-warning');
            const kurang = saldo !== null && terpakai > 0 && saldo - terpakai < 0;

            if (saldo !== null) {
                const sisa = saldo - terpakai;
                sisaEl.textContent = sisa.toLocaleString('id-ID') + ' poin';
                sisaEl.style.color = sisa < 0 ? 'var(--critical)' : 'var(--good)';
            } else {
                sisaEl.textContent = '—';
                sisaEl.style.color = '';
            }

            submitBtn.disabled = kurang;
            submitBtn.style.opacity = kurang ? '0.5' : '';
            submitBtn.style.cursor = kurang ? 'not-allowed' : '';
            warning.style.display = kurang ? '' : 'none';

            const preview = document.getElementById('note-preview');
            const isDiuangkan = document.getElementById('keterangan-select').value === 'di-uangkan';

            if (isDiuangkan && isEdit) {
                const rewardSel = document.getElementById('reward-select');
                const rewardOpt = rewardSel.options[rewardSel.selectedIndex];
                if (rewardOpt && rewardOpt.value) {
                    const harga = parseFloat(rewardOpt.dataset.harga) || 0;
                    const budget = rewardOpt.dataset.budget ? parseFloat(rewardOpt.dataset.budget) : null;
                    preview.innerHTML = 'Harga Reward: ' + rp(harga) + (budget !== null ? ' &middot; Budget Reward: ' + rp(budget) : '');
                    preview.style.display = '';
                } else {
                    preview.style.display = 'none';
                }
            } else if (isDiuangkan && !isEdit && rewardItems.length) {
                preview.innerHTML = rewardItems.map(item => {
                    const r = rewardCatalog.find(x => x.id === item.reward_catalog_id);
                    const harga = r ? r.harga : 0;
                    const budget = r && r.budget !== null ? r.budget : null;
                    return item.nama + ': Harga ' + rp(harga) + (budget !== null ? ' &middot; Budget ' + rp(budget) : '');
                }).join('<br>');
                preview.style.display = '';
            } else {
                preview.style.display = 'none';
            }
        }

        document.getElementById('redemption-form').addEventListener('submit', function (e) {
            if (!isEdit && rewardItems.length === 0) {
                e.preventDefault();
                alert('Pilih minimal satu reward.');
            }
        });

        updateSaldo();
        @unless ($redemption)
        renderRewardItems();
        @endunless
        recalc();
    </script>
@endsection
