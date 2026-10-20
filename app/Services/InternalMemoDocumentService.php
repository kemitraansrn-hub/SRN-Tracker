<?php

namespace App\Services;

use App\Models\AkuisisiReferral;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * "Print IM" di Akuisisi > Tracking Referral — surat Internal Memo
 * pengajuan pembayaran bonus Program Referral per tahap transaksi.
 * Dokumen "SRN - IM REFFERAL PROGRAM FIX.pdf" (2026-10-20) adalah format
 * RESMI/final (dikonfirmasi user) -- bukan contoh sementara. Method sama
 * persis kayak MouDocumentService (Special Deal) -- PhpWord -> .docx,
 * bukan PDF. Nomor memo ("03.288/SRN/X/2026") diisi manual oleh admin pas
 * nge-print (gak ada penomoran otomatis di sistem), sisanya otomatis dari
 * data AkuisisiReferral + tahap yang dipilih.
 */
class InternalMemoDocumentService
{
    private const SIGNERS = [
        ['Mohammad Viekry', 'Sales & Development Manager'],
        ['Muhammad Hilmi Putra A', 'General Manager SRN'],
        ['Diar Prakasa Yudha', 'Finance Manager PT. SAS'],
    ];

    public static function build(AkuisisiReferral $referral, object $tahap, string $noMemo): PhpWord
    {
        $referral->loadMissing('mitra', 'pengakuisisi');

        Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);
        // Default paragraph Word punya spaceAfter ~200 twips bawaan --
        // dikali belasan paragraf/bullet di dokumen ini, itu nambah hampir
        // 1 halaman penuh sendirian. Dimatikan di sini, jarak antar bagian
        // diatur manual lewat addTextBreak ukuran kecil di bawah.
        $phpWord->setDefaultParagraphStyle(['spaceAfter' => 0, 'spaceBefore' => 0, 'lineHeight' => 1.0]);

        // Margin lebih kecil dari MouDocumentService (yang 2 halaman) --
        // dokumen ini WAJIB muat 1 halaman sesuai contoh resmi.
        $section = $phpWord->addSection([
            'marginTop' => 450, 'marginBottom' => 450, 'marginLeft' => 1100, 'marginRight' => 1100,
            'headerHeight' => 900, 'footerHeight' => 350,
        ]);

        self::addLetterhead($section->addHeader());
        self::addFooter($section->addFooter());
        self::addBody($section, $referral, $tahap, $noMemo);

        return $phpWord;
    }

    public static function fileName(AkuisisiReferral $referral, object $tahap): string
    {
        $mitra = $referral->mitra->nama ?? 'Mitra';
        $safe = trim(preg_replace('/[\\\\\/:*?"<>|]/', '', $mitra));

        return 'IM Referral '.$safe.' - Transaksi '.$tahap->tahap.'.docx';
    }

    private static function logoPath(string $name): string
    {
        return storage_path('app/mou-assets/'.$name);
    }

    private static function addLetterhead(AbstractContainer $container): void
    {
        $table = $container->addTable(['cellMarginTop' => 0, 'cellMarginBottom' => 0]);
        $table->addRow();
        $left = $table->addCell(1600, ['valign' => 'center']);
        $left->addImage(self::logoPath('logo-srn.png'), ['width' => 55, 'height' => 30]);

        $center = $table->addCell(7900, ['valign' => 'center']);
        $center->addText('SINERGI RETAIL NETWORK', ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER]);
        $center->addText('Jalan Jabaru IV Nomor 7A, RT 004, RW 005 Pasir Kuda Bogor Barat,', ['size' => 9], ['alignment' => Jc::CENTER]);
        $center->addText('Kota Bogor, Jawa Barat 16119', ['size' => 9], ['alignment' => Jc::CENTER]);
        $center->addText('Telp: 087780840947', ['size' => 9], ['alignment' => Jc::CENTER]);

        $ruleTable = $container->addTable();
        $ruleTable->addRow(20);
        $ruleTable->addCell(9500, ['borderBottomSize' => 12, 'borderBottomColor' => '000000']);
        $container->addTextBreak(1, ['size' => 4]);
    }

    private static function addFooter(AbstractContainer $footer): void
    {
        $run = $footer->addTextRun(['alignment' => Jc::END]);
        $run->addField('PAGE');
        $run->addText(' | Page', ['size' => 9]);
    }

    private static function rp(float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    private static function addBody($section, AkuisisiReferral $referral, object $tahap, string $noMemo): void
    {
        $mitraNama = $referral->mitra->nama ?? '—';
        $namaPengakuisisi = $referral->nama_pengakuisisi;
        $noRekening = $referral->pengakuisisi?->no_rekening;
        $bank = $referral->pengakuisisi?->bank;
        $rekeningLabel = $noRekening ? $noRekening.($bank ? ' ('.$bank.')' : '') : '—';

        $section->addText('INTERNAL MEMO', ['bold' => true, 'underline' => 'single'], ['alignment' => Jc::CENTER]);
        $section->addText($noMemo, [], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1, ['size' => 8]);

        $section->addText('Perihal : Pengajuan Pembayaran Bonus Program Refferal');
        $section->addTextBreak(1, ['size' => 8]);

        $intro = $section->addTextRun(['alignment' => Jc::BOTH]);
        $intro->addText('Sehubungan dengan pencapaian dan pelaksanaan Program Referral, bersama ini kami mengajukan permohonan pencairan ');
        $intro->addText('benefit', ['italic' => true]);
        $intro->addText(' referral bagi Pengakuisisi atas transaksi yang dilakukan dengan detail sebagai berikut:');
        $section->addTextBreak(1, ['size' => 8]);

        // Bullet list persis kayak template resmi (bukan tabel) -- label
        // rata kiri dengan lebar tab tetap biar titik dua-nya sejajar.
        $rincian = [
            ['Nama Mitra', $mitraNama],
            ['Tanggal Join Mitra', $referral->tanggal_akuisisi->translatedFormat('d F Y')],
            ['Transaksi ke', (string) $tahap->tahap],
            ['Nominal Order', self::rp($tahap->nominal)],
            ['Nama Pengakuisisi', $namaPengakuisisi],
            ['Nomor Rekening Pengakuisisi', $rekeningLabel],
            ['Persentase Bonus', $tahap->persentase.'%'],
            ['Nominal Bonus', self::rp($tahap->nominal_bonus)],
        ];
        $tabStops = ['tabs' => [new \PhpOffice\PhpWord\Style\Tab('left', 3200)], 'spaceAfter' => 40];
        foreach ($rincian as [$label, $value]) {
            $run = $section->addListItemRun(0, null, $tabStops);
            $run->addText($label);
            $run->addText("\t: ".$value);
        }
        $section->addTextBreak(1, ['size' => 8]);

        $penutup1 = $section->addTextRun(['alignment' => Jc::BOTH]);
        $penutup1->addText('Pengajuan ');
        $penutup1->addText('benefit', ['italic' => true]);
        $penutup1->addText(' ini disampaikan sebagai bentuk apresiasi atas kontribusi Pengakuisisi dalam memperluas jaringan mitra, serta untuk menjaga motivasi dan kelancaran pencapaian target penjualan pada ');
        $penutup1->addText('channel', ['italic' => true]);
        $penutup1->addText(' terkait.');
        $section->addTextBreak(1, ['size' => 8]);

        $section->addText('Demikian Internal Memo ini kami sampaikan untuk dapat dipertimbangkan dan disetujui. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.', [], ['alignment' => Jc::BOTH]);
        $section->addTextBreak(1, ['size' => 8]);

        $section->addText('Bogor, '.now()->translatedFormat('d F Y'), [], ['alignment' => Jc::CENTER]);
        $section->addText('Salam hangat,', [], ['alignment' => Jc::CENTER]);
        // Dikasih ruang lebih lega sebelum tanda tangan (2026-10-20, user
        // minta ditambah jaraknya) -- masih tetap 1 halaman karena bagian
        // atas dokumen sudah dirapatkan di update sebelumnya.
        $section->addTextBreak(4, ['size' => 16]);

        $signTable = $section->addTable();
        $signTable->addRow();
        foreach (self::SIGNERS as [$name, $role]) {
            $cell = $signTable->addCell(3166);
            $cell->addText($name, ['bold' => true], ['alignment' => Jc::CENTER]);
            $cell->addText($role, [], ['alignment' => Jc::CENTER]);
        }
    }
}
