<?php

namespace App\Services\AkuisisiLeadImport;

use App\Models\AkuisisiBusinessStatus;
use App\Models\AkuisisiCapitalStatus;
use App\Models\AkuisisiChannel;
use App\Models\AkuisisiFollowupStatus;
use App\Models\AkuisisiInterestLevel;
use App\Models\AkuisisiPipelineStage;

/**
 * Kolom template import lead. Satu sumber untuk template, pembaca file, validasi, dan laporan error.
 * Diporting dari App\Services\LeadImport\ImportColumns di PartnerAcquire CRM, DITAMBAH 2 kolom
 * ('followup_result', 'next_followup_date') -- permintaan eksplisit 2026-11-02: tanpa migrasi
 * otomatis dari DB lama, data lead lama di-import manual lewat Excel, jadi template ini perlu bisa
 * bawa status follow-up & jadwal next follow-up yang SUDAH BERJALAN, bukan cuma lead baru kosongan.
 * Isi salah satu dari 2 kolom itu -> baris ditandai `is_legacy` (lihat AkuisisiLeadImportValidator).
 */
class AkuisisiImportColumns
{
    public const MAX_ROWS = 10000;

    public const SHEET_NAME = 'Data Lead';

    /**
     * key => [judul kolom, wajib, alias judul lain yang diterima, model master untuk dropdown]
     */
    public static function all(): array
    {
        return [
            'entry_date' => ['Tanggal Masuk', true, ['tanggal', 'tanggal masuk lead', 'tgl masuk'], null],
            'name' => ['Nama Lead', true, ['nama', 'nama calon mitra'], null],
            'whatsapp' => ['Nomor WhatsApp', true, ['nomor wa', 'no wa', 'whatsapp', 'no whatsapp', 'nomor hp'], null],
            'city' => ['Kota', false, ['kota / wilayah', 'wilayah', 'domisili'], null],
            'channel' => ['Channel Sumber', true, ['channel', 'sumber'], AkuisisiChannel::class],
            'business' => ['Status Bisnis', false, [], AkuisisiBusinessStatus::class],
            'interest' => ['Respon / Minat Lead', false, ['respon minat', 'respon', 'minat'], AkuisisiInterestLevel::class],
            'capital' => ['Status Modal', false, ['modal'], AkuisisiCapitalStatus::class],
            'stage' => ['Tahapan Pipeline', false, ['pipeline', 'tahapan'], AkuisisiPipelineStage::class],
            'followup_result' => ['Hasil Follow Up', false, ['hasil follow up', 'status follow up', 'hasil followup'], AkuisisiFollowupStatus::class],
            'next_followup_date' => ['Tanggal Next Follow Up', false, ['next follow up', 'tanggal next followup', 'jadwal next follow up'], null],
            'note' => ['Catatan', false, ['catatan awal', 'keterangan'], null],
        ];
    }

    public static function headers(): array
    {
        return array_values(array_map(fn (array $column) => $column[0], self::all()));
    }

    /** Samakan judul/isi untuk pencocokan: huruf kecil, tanpa spasi berlebih & tanda baca. */
    public static function normalize(?string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower((string) $value)));
    }

    /**
     * Petakan judul kolom file ke key kolom. Mengembalikan [indeks kolom => key] dan judul yang tidak dikenal.
     *
     * @return array{0: array<int, string>, 1: string[]}
     */
    public static function mapHeaders(array $headers): array
    {
        $lookup = [];
        foreach (self::all() as $key => [$title, , $aliases]) {
            foreach ([$title, ...$aliases] as $label) {
                $lookup[self::normalize($label)] = $key;
            }
        }

        $mapped = [];
        $unknown = [];
        foreach ($headers as $index => $header) {
            $normalized = self::normalize($header);
            if ($normalized === '') {
                continue;
            }
            if (isset($lookup[$normalized]) && ! in_array($lookup[$normalized], $mapped, true)) {
                $mapped[$index] = $lookup[$normalized];
            } else {
                $unknown[] = (string) $header;
            }
        }

        return [$mapped, $unknown];
    }
}
