<?php

namespace Database\Seeders;

use App\Models\KotaKabupaten;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Data 514 kabupaten/kota + 34 provinsi dari dataset publik
 * azishapidin/indoregion (github.com/azishapidin/indoregion), CSV-nya
 * disimpan di database/seeders/data/. Idempotent — aman dijalankan ulang,
 * gak bikin duplikat (truncate dulu tiap run).
 */
class KotaKabupatenSeeder extends Seeder
{
    private const PROVINSI_SINGKATAN = ['Dki' => 'DKI', 'Di' => 'DI'];

    public function run(): void
    {
        $dir = database_path('seeders/data');

        $provinsi = [];
        foreach ($this->readCsv($dir.'/indonesia-provinces.csv') as $row) {
            $provinsi[$row['id']] = $this->titleCase($row['name']);
        }

        KotaKabupaten::truncate();

        $rows = [];
        $now = now();
        foreach ($this->readCsv($dir.'/indonesia-regencies.csv') as $row) {
            $rows[] = [
                'nama' => $this->titleCase($row['name']),
                'provinsi' => $provinsi[$row['province_id']] ?? '',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            KotaKabupaten::insert($chunk);
        }

        $this->command?->info(count($rows).' kabupaten/kota berhasil di-seed.');
    }

    private function titleCase(string $value): string
    {
        $title = ucwords(mb_strtolower($value));

        foreach (self::PROVINSI_SINGKATAN as $wrong => $right) {
            $title = preg_replace('/\b'.$wrong.'\b/', $right, $title);
        }

        return $title;
    }

    private function readCsv(string $path): \Generator
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        while (($line = fgetcsv($handle)) !== false) {
            yield array_combine($header, $line);
        }

        fclose($handle);
    }
}
