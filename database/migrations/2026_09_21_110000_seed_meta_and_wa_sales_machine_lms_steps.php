<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $daftar = [
            'meta' => [
                'Persiapan Iklan Meta Ads',
                '3 Kunci Utama (Fanspage , Instagram & Whatsapp)',
                'Praktek Membuat Fanspage Facebook',
                'Praktek Optimasi Fanspage Facebook',
                'Praktek Tautkan WA di Fanspage & Instagram',
                'Role Admin di Fanspage Facebook',
                'Membuat Akun Iklan',
                'Set Up Metode Pembayaran Iklan',
                'Menambah Metode Pembayaran di Akun Iklan',
                'Cheklist Wajib Sebelum Iklan',
            ],
            'wa_sales_machine' => [
                'Mindset & Target',
                'Set Up Profil Whatsapp Bisnis',
                'Set Up Katalog Whatsapp',
                'Praktek Set Up Katalog Whatsapp',
                'Bangun Database Kontak Bag 1',
                'Bangun Database Kontak Bag 2',
                'Lead Magnet Digital',
                'Copywriting Formula',
            ],
        ];

        foreach ($daftar as $platform => $judulList) {
            // Lewati kalau platform ini sudah punya video (mis. sudah diinput
            // manual lewat menu Master LMS) supaya tidak dobel.
            if (DB::table('lms_steps')->where('platform', $platform)->exists()) {
                continue;
            }

            DB::table('lms_steps')->insert(array_map(fn ($judul, $i) => [
                'platform' => $platform,
                'urutan' => $i + 1,
                'judul' => $judul,
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ], $judulList, array_keys($judulList)));
        }
    }

    public function down(): void
    {
        DB::table('lms_steps')->whereIn('platform', ['meta', 'wa_sales_machine'])->delete();
    }
};
