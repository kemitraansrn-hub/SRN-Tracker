<?php

namespace App\Services;

use App\Models\SpecialDeal;
use App\Support\Terbilang;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;

class MouDocumentService
{
    private const SIGNER_MENGETAHUI = ['Mohamad Viekry', 'Reseller Management Lead'];

    private const SIGNER_MENYETUJUI = ['Revardi Syahputra', 'Chief Marketing Officer PT. SAS'];

    public static function build(SpecialDeal $deal): PhpWord
    {
        $deal->loadMissing('mitra', 'kae');

        Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 850, 'marginBottom' => 850, 'marginLeft' => 1100, 'marginRight' => 1100,
            'headerHeight' => 1500, 'footerHeight' => 600,
        ]);

        self::addLetterhead($section->addHeader());
        self::addFooter($section->addFooter());
        self::addPageOne($section, $deal);
        $section->addPageBreak();
        self::addPageTwo($section, $deal);

        return $phpWord;
    }

    public static function fileName(SpecialDeal $deal): string
    {
        $mitra = $deal->mitra->nama ?? 'Mitra';
        $safe = trim(preg_replace('/[\\\\\/:*?"<>|]/', '', $mitra));

        return $safe.' Q'.$deal->kuartal.' '.$deal->tahun.'.docx';
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
        $left->addImage(self::logoPath('logo-sas.png'), ['width' => 55, 'height' => 30]);

        $center = $table->addCell(6300, ['valign' => 'center']);
        $center->addText('SINERGI RETAIL NETWORK', ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER]);
        $center->addText('Jalan Pelita Jaya II No 23 RT 02 RW 06, Kelurahan Kedung Jaya,', ['size' => 9], ['alignment' => Jc::CENTER]);
        $center->addText('Kecamatan Tanah Sareal, Kota Bogor, 16164', ['size' => 9], ['alignment' => Jc::CENTER]);
        $center->addText('Telp: 0851773142249', ['size' => 9], ['alignment' => Jc::CENTER]);

        $right = $table->addCell(1600, ['valign' => 'center']);
        $right->addImage(self::logoPath('logo-srn.png'), ['width' => 55, 'height' => 30]);

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

    private static function rp(?float $value): string
    {
        return $value !== null ? 'Rp'.number_format($value, 0, ',', '.') : '—';
    }

    private static function rpTerbilang(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return self::rp($value).' -, ('.Terbilang::terbilang((int) round($value)).' Rupiah)';
    }

    private static function addPageOne($section, SpecialDeal $deal): void
    {
        $mitraNama = $deal->mitra->nama ?? '—';
        $kaeNama = $deal->kae->name ?? '—';
        $channel = $deal->segmen ? ucwords(strtolower($deal->segmen)) : '—';
        $subsidiLabel = $deal->subsidi ?: 'Voucher Belanja';
        $tanggalSurat = now()->translatedFormat('d F Y');

        [$mulai, $selesai] = $deal->periodeRange() ?? [null, null];
        $periode = $mulai && $selesai
            ? $mulai->translatedFormat('d F').' s/d '.$selesai->translatedFormat('d F Y').' (Q'.$deal->kuartal.')'
            : '—';

        $section->addTextBreak(1);
        $section->addText('Memorandum of Understanding (MoU)', ['bold' => true, 'size' => 13], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);

        $perihalTable = $section->addTable(['cellMarginTop' => 0, 'cellMarginBottom' => 0]);
        $perihalTable->addRow();
        $perihalTable->addCell(5500)->addText('Perihal: Program Sinergi Special Deal (SSD)');
        $tanggalCell = $perihalTable->addCell(4000);
        $tanggalCell->addText($tanggalSurat, [], ['alignment' => Jc::END]);
        $tanggalCell->addText('Bogor, Jawa Barat, Indonesia', [], ['alignment' => Jc::END]);
        $section->addTextBreak(1);

        $pihakTable = $section->addTable(['cellMarginTop' => 0, 'cellMarginBottom' => 0]);
        $pihakTable->addRow();
        $pertama = $pihakTable->addCell(4750);
        $pertama->addText('Pihak Pertama:');
        $pertama->addText($kaeNama);
        $pertama->addText('Key Account Executive', ['bold' => true]);
        $kedua = $pihakTable->addCell(4750);
        $kedua->addText('Pihak Kedua:');
        $kedua->addText($mitraNama);
        $kedua->addText('Mitra Sinergi Retail Network', ['bold' => true]);
        $section->addTextBreak(1);

        $section->addText('A. Pendahuluan', ['bold' => true]);
        $intro = $section->addTextRun(['alignment' => Jc::BOTH]);
        $intro->addText('Pihak Pertama dan Pihak Kedua sepakat untuk menjalin kerjasama dalam program ');
        $intro->addText('Sinergi Special Deal (SSD).', ['bold' => true]);
        $intro->addText(' Program ini bertujuan sebagai bentuk ');
        $intro->addText('reward', ['italic' => true]);
        $intro->addText(' sekaligus ');
        $intro->addText('support', ['italic' => true]);
        $intro->addText(' pada Pihak Kedua dalam rangka meningkatkan penjualan produk-produk Sinergi Group.');
        $section->addTextBreak(1);

        $section->addText('B. Ruang Lingkup', ['bold' => true]);
        $section->addText('Program Sinergi Special Deal (SSD) ini meliputi:');

        $rincian = [
            ['Nama Mitra', $mitraNama],
            ['Channel Mitra', $channel],
            ['Deskripsi Program', 'Program subsidi '.$subsidiLabel.' berupa uang tunai kepada mitra yang berhasil mencapai target yang telah disepakati dalam MoU ini.'],
            ['Target', self::rpTerbilang($deal->target_kuartal !== null ? (float) $deal->target_kuartal : null)],
            ['Reward', self::rpTerbilang($deal->nominalReward())],
            ['Periode Program', $periode],
        ];
        $rincianTable = $section->addTable(['cellMarginTop' => 40, 'cellMarginBottom' => 40]);
        foreach ($rincian as [$label, $value]) {
            $rincianTable->addRow();
            $rincianTable->addCell(2400)->addText($label);
            $rincianTable->addCell(300)->addText(':');
            $rincianTable->addCell(6800)->addText($value, [], ['alignment' => Jc::BOTH]);
        }
        $section->addTextBreak(1);

        $section->addText('C. Syarat dan Ketentuan', ['bold' => true]);
        $section->addText('Dalam keikut sertaan program ini, terdapat syarat dan ketentuan sebagai berikut:');
        $section->addListItem('Pencapaian akan dihitung setelah periode program berakhir, selambat lambatnya 7 hari kerja.', 0);
        $section->addListItem('Subsidi '.$subsidiLabel.' akan diberikan setelah proses penghitungan pencapaian selesai, selambat lambatnya 7 hari kerja.', 0);
        $section->addListItem('MoU ini bersifat rahasia (confidential), oleh karyawan SRN & Mitra berkomitmen penuh untuk menjaga informasi apapun yang ada pada MoU ini.', 0);
    }

    private static function addPageTwo($section, SpecialDeal $deal): void
    {
        $kaeNama = $deal->kae->name ?? '—';
        $mitraNama = $deal->mitra->nama ?? '—';

        $section->addTextBreak(1);
        $section->addText('Hormat Kami,');
        $section->addTextBreak(2);

        $mengajukan = $section->addTable();
        $mengajukan->addRow();
        self::addSignatureCell($mengajukan, 'Mengajukan,', $kaeNama, 'Key Account Executive');
        self::addSignatureCell($mengajukan, 'Mengajukan,', $mitraNama, 'Mitra');

        $section->addTextBreak(3);

        $mengetahui = $section->addTable();
        $mengetahui->addRow();
        self::addSignatureCell($mengetahui, 'Mengetahui,', self::SIGNER_MENGETAHUI[0], self::SIGNER_MENGETAHUI[1]);
        self::addSignatureCell($mengetahui, 'Menyetujui,', self::SIGNER_MENYETUJUI[0], self::SIGNER_MENYETUJUI[1]);
    }

    private static function addSignatureCell($table, string $heading, string $name, string $role): void
    {
        $cell = $table->addCell(4750);
        $cell->addText($heading, [], ['alignment' => Jc::CENTER]);
        $cell->addTextBreak(3);
        $cell->addText($name, ['underline' => 'single'], ['alignment' => Jc::CENTER]);
        $cell->addText($role, ['bold' => true], ['alignment' => Jc::CENTER]);
    }
}
