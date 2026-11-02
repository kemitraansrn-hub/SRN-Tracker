<?php

namespace App\Services\AkuisisiLeadImport;

use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Throwable;

/**
 * Membaca file .xlsx / .csv menjadi judul kolom + baris data (nilai mentah, tanpa format tampilan).
 * Sheet "Data Lead" dipakai bila ada; selain itu sheet pertama. Diporting dari
 * App\Services\LeadImport\SpreadsheetReader di PartnerAcquire CRM.
 */
class AkuisisiSpreadsheetReader
{
    /**
     * @return array{headers: array<int, mixed>, rows: array<int, array<int, mixed>>} rows di-key nomor baris file
     */
    public function read(string $path, string $extension): array
    {
        try {
            $reader = match (strtolower($extension)) {
                'xlsx' => new Xlsx,
                'csv', 'txt' => (new Csv)->setInputEncoding('UTF-8')->setDelimiter(null),
                default => throw new AkuisisiImportFileException('Format file harus .xlsx atau .csv.'),
            };
            $reader->setReadDataOnly(true);

            if ($reader instanceof Xlsx && in_array(AkuisisiImportColumns::SHEET_NAME, $reader->listWorksheetNames($path), true)) {
                $reader->setLoadSheetsOnly([AkuisisiImportColumns::SHEET_NAME]);
            }

            $sheet = $reader->load($path)->getActiveSheet();
        } catch (AkuisisiImportFileException $e) {
            throw $e;
        } catch (Throwable) {
            throw new AkuisisiImportFileException('File tidak bisa dibaca. Pastikan file .xlsx atau .csv yang valid (bukan file yang dikunci atau rusak).');
        }

        $data = $sheet->toArray(null, true, false, false);
        $headers = array_map(fn ($value) => is_string($value) ? preg_replace('/^\x{FEFF}/u', '', $value) : $value, array_shift($data) ?? []);

        $rows = [];
        foreach ($data as $offset => $row) {
            if (collect($row)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                continue;
            }
            $rows[$offset + 2] = $row;

            if (count($rows) > AkuisisiImportColumns::MAX_ROWS) {
                throw new AkuisisiImportFileException('File berisi lebih dari '.number_format(AkuisisiImportColumns::MAX_ROWS, 0, ',', '.').' baris data. Pecah menjadi beberapa file.');
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
