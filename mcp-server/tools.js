// Definisi tools MCP (read-only) buat data SRN Link Center — dipakai
// bareng oleh index.js (stdio, lokal) dan http-server.js (remote+token).
const { z } = require('zod');

const EFFECTIVE_TARGET_SQL = `CASE tb.tier_dipakai
    WHEN 'komit' THEN tb.komit
    WHEN 'stretch' THEN tb.stretch
    ELSE tb.target
END`;

const now = () => new Date();
const defBulan = (b) => b || now().getMonth() + 1;
const defTahun = (t) => t || now().getFullYear();

function rp(n) {
    const v = Number(n || 0);
    return 'Rp' + v.toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

function textResult(obj) {
    return { content: [{ type: 'text', text: JSON.stringify(obj, null, 2) }] };
}

/** @param {import('@modelcontextprotocol/sdk/server/mcp.js').McpServer} server */
function registerTools(server, pool) {
    server.registerTool(
        'cari_mitra',
        {
            title: 'Cari Mitra',
            description: 'Cari mitra (reseller) berdasarkan nama atau kode mitra (partial match). Balikin daftar kandidat kalau ada beberapa yang cocok.',
            inputSchema: { query: z.string().describe('Kata kunci nama atau kode mitra') },
        },
        async ({ query }) => {
            const [rows] = await pool.query(
                `SELECT id, kode_mitra, nama, kae_code, status
                 FROM mitra
                 WHERE nama LIKE ? OR kode_mitra LIKE ?
                 ORDER BY nama LIMIT 15`,
                [`%${query}%`, `%${query}%`]
            );
            return textResult({ jumlah: rows.length, hasil: rows });
        }
    );

    server.registerTool(
        'omset_mitra',
        {
            title: 'Omset & Target Mitra',
            description: 'Lihat omset, target, dan persentase pencapaian 1 mitra untuk bulan tertentu (default bulan berjalan). Cari mitra dulu pakai nama/kode kalau belum tau ID/kode persisnya.',
            inputSchema: {
                mitra: z.string().describe('Nama atau kode mitra (kalau ada lebih dari 1 kecocokan, akan dikasih daftar kandidat)'),
                bulan: z.number().int().min(1).max(12).optional().describe('1-12, default bulan berjalan'),
                tahun: z.number().int().optional().describe('contoh 2026, default tahun berjalan'),
            },
        },
        async ({ mitra, bulan, tahun }) => {
            const b = defBulan(bulan);
            const t = defTahun(tahun);

            const [candidates] = await pool.query(
                `SELECT id, kode_mitra, nama, kae_code FROM mitra WHERE nama LIKE ? OR kode_mitra = ? ORDER BY nama LIMIT 10`,
                [`%${mitra}%`, mitra]
            );
            if (candidates.length === 0) {
                return textResult({ error: `Mitra "${mitra}" tidak ditemukan.` });
            }
            if (candidates.length > 1 && !candidates.some((c) => c.kode_mitra === mitra)) {
                return textResult({
                    info: `Ada ${candidates.length} mitra yang cocok, sebutkan kode_mitra persisnya:`,
                    kandidat: candidates,
                });
            }
            const m = candidates.find((c) => c.kode_mitra === mitra) || candidates[0];

            const [[target]] = await pool.query(
                `SELECT tb.segmen, tb.tier_dipakai, tb.komit, tb.target, tb.stretch,
                        ${EFFECTIVE_TARGET_SQL} as effective_target
                 FROM target_bulanan tb
                 WHERE tb.mitra_id = ? AND tb.bulan = ? AND tb.tahun = ?`,
                [m.id, b, t]
            );
            const [[omsetRow]] = await pool.query(
                `SELECT COALESCE(SUM(total_transaksi), 0) as omset, COUNT(*) as jumlah_order
                 FROM orders WHERE mitra_id = ? AND YEAR(tanggal_order) = ? AND MONTH(tanggal_order) = ?`,
                [m.id, t, b]
            );

            const effTarget = target ? Number(target.effective_target) : 0;
            const omset = Number(omsetRow.omset);
            const pct = effTarget > 0 ? Math.round((omset / effTarget) * 1000) / 10 : null;

            return textResult({
                mitra: { kode_mitra: m.kode_mitra, nama: m.nama, kae_code: m.kae_code },
                periode: `${b}/${t}`,
                segmen: target?.segmen ?? 'Belum ditarget bulan ini',
                target: effTarget,
                target_formatted: rp(effTarget),
                omset,
                omset_formatted: rp(omset),
                jumlah_order: omsetRow.jumlah_order,
                pencapaian_persen: pct,
            });
        }
    );

    server.registerTool(
        'mitra_belum_belanja',
        {
            title: 'Mitra Belum Belanja',
            description: 'List mitra yang punya target bulan ini tapi omsetnya masih 0 (belum belanja sama sekali) atau di bawah target. Berguna buat follow-up.',
            inputSchema: {
                bulan: z.number().int().min(1).max(12).optional(),
                tahun: z.number().int().optional(),
                kae_code: z.string().optional().describe('Filter cuma mitra KAE tertentu, kosongkan buat semua KAE'),
                hanya_nol: z.boolean().optional().describe('true = cuma yang omsetnya 0 sama sekali; false/kosong = semua yang di bawah target'),
                limit: z.number().int().min(1).max(100).optional(),
            },
        },
        async ({ bulan, tahun, kae_code, hanya_nol, limit }) => {
            const b = defBulan(bulan);
            const t = defTahun(tahun);
            const lim = limit || 30;

            const params = [t, b, t, b];
            let kaeFilter = '';
            if (kae_code) {
                kaeFilter = 'AND m.kae_code = ?';
                params.push(kae_code);
            }

            const [rows] = await pool.query(
                `SELECT m.kode_mitra, m.nama, m.kae_code, tb.segmen,
                        ${EFFECTIVE_TARGET_SQL} as target,
                        COALESCE((SELECT SUM(total_transaksi) FROM orders o WHERE o.mitra_id = m.id AND YEAR(o.tanggal_order) = ? AND MONTH(o.tanggal_order) = ?), 0) as omset
                 FROM target_bulanan tb
                 JOIN mitra m ON m.id = tb.mitra_id
                 WHERE tb.tahun = ? AND tb.bulan = ? ${kaeFilter}
                 HAVING omset ${hanya_nol ? '= 0' : '< target'}
                 ORDER BY target DESC
                 LIMIT ?`,
                [...params, lim]
            );

            return textResult({
                periode: `${b}/${t}`,
                kriteria: hanya_nol ? 'omset = 0' : 'omset < target',
                jumlah: rows.length,
                mitra: rows.map((r) => ({
                    kode_mitra: r.kode_mitra,
                    nama: r.nama,
                    kae_code: r.kae_code,
                    segmen: r.segmen,
                    target: rp(r.target),
                    omset: rp(r.omset),
                })),
            });
        }
    );

    server.registerTool(
        'total_omset',
        {
            title: 'Total Omset Periode',
            description: 'Total omset + total target + persentase pencapaian gabungan semua mitra (atau 1 KAE tertentu) untuk bulan/tahun tertentu.',
            inputSchema: {
                bulan: z.number().int().min(1).max(12).optional(),
                tahun: z.number().int().optional(),
                kae_code: z.string().optional().describe('Kosongkan buat total perusahaan, isi buat total 1 KAE aja'),
            },
        },
        async ({ bulan, tahun, kae_code }) => {
            const b = defBulan(bulan);
            const t = defTahun(tahun);

            const omsetParams = [t, b];
            let omsetKaeFilter = '';
            if (kae_code) {
                omsetKaeFilter = 'AND kae_code = ?';
                omsetParams.push(kae_code);
            }
            const [[omsetRow]] = await pool.query(
                `SELECT COALESCE(SUM(total_transaksi), 0) as omset, COUNT(*) as jumlah_order
                 FROM orders WHERE YEAR(tanggal_order) = ? AND MONTH(tanggal_order) = ? ${omsetKaeFilter}`,
                omsetParams
            );

            const targetParams = [t, b];
            let targetKaeFilter = '';
            if (kae_code) {
                targetKaeFilter = 'AND m.kae_code = ?';
                targetParams.push(kae_code);
            }
            const [[targetRow]] = await pool.query(
                `SELECT COALESCE(SUM(${EFFECTIVE_TARGET_SQL}), 0) as total_target
                 FROM target_bulanan tb JOIN mitra m ON m.id = tb.mitra_id
                 WHERE tb.tahun = ? AND tb.bulan = ? ${targetKaeFilter}`,
                targetParams
            );

            const omset = Number(omsetRow.omset);
            const target = Number(targetRow.total_target);
            const pct = target > 0 ? Math.round((omset / target) * 1000) / 10 : null;

            return textResult({
                periode: `${b}/${t}`,
                scope: kae_code || 'Semua KAE (perusahaan)',
                total_target: target,
                total_target_formatted: rp(target),
                total_omset: omset,
                total_omset_formatted: rp(omset),
                jumlah_order: omsetRow.jumlah_order,
                pencapaian_persen: pct,
            });
        }
    );

    server.registerTool(
        'pencapaian_segmen',
        {
            title: 'Pencapaian per Segmen',
            description: 'Breakdown target vs omset per segmen (Pareto, RTP, Reguler, Special Reguler) untuk bulan/tahun tertentu.',
            inputSchema: {
                bulan: z.number().int().min(1).max(12).optional(),
                tahun: z.number().int().optional(),
            },
        },
        async ({ bulan, tahun }) => {
            const b = defBulan(bulan);
            const t = defTahun(tahun);

            const [rows] = await pool.query(
                `SELECT tb.segmen,
                        COUNT(*) as jumlah_mitra,
                        SUM(${EFFECTIVE_TARGET_SQL}) as total_target,
                        COALESCE(SUM((SELECT SUM(total_transaksi) FROM orders o WHERE o.mitra_id = tb.mitra_id AND YEAR(o.tanggal_order) = ? AND MONTH(o.tanggal_order) = ?)), 0) as total_omset
                 FROM target_bulanan tb
                 WHERE tb.tahun = ? AND tb.bulan = ?
                 GROUP BY tb.segmen
                 ORDER BY total_target DESC`,
                [t, b, t, b]
            );

            return textResult({
                periode: `${b}/${t}`,
                segmen: rows.map((r) => {
                    const target = Number(r.total_target);
                    const omset = Number(r.total_omset);
                    return {
                        segmen: r.segmen,
                        jumlah_mitra: r.jumlah_mitra,
                        target: rp(target),
                        omset: rp(omset),
                        pencapaian_persen: target > 0 ? Math.round((omset / target) * 1000) / 10 : null,
                    };
                }),
            });
        }
    );
}

function createPool(mysql, env) {
    return mysql.createPool({
        host: env.DB_HOST || '127.0.0.1',
        port: Number(env.DB_PORT || 3306),
        user: env.DB_USERNAME || 'root',
        password: env.DB_PASSWORD || '',
        database: env.DB_DATABASE || 'srn',
        waitForConnections: true,
        connectionLimit: 3,
    });
}

module.exports = { registerTools, createPool };
