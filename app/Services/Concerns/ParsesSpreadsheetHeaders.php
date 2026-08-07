<?php

namespace App\Services\Concerns;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Shared helpers for reading a spreadsheet's header row and matching it
 * against a set of accepted column-name aliases, since real-world exports
 * don't always use exactly the header text we expect.
 */
trait ParsesSpreadsheetHeaders
{
    private function readHeaderMap(Worksheet $sheet): array
    {
        $map = [];
        $columnIndex = 1;

        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $value = trim((string) $cell->getValue());
                if ($value !== '') {
                    $map[strtoupper($value)] = $columnIndex;
                }
                $columnIndex++;
            }
        }

        return $map;
    }

    private function resolveAliases(array $headerMap, array $aliasGroups): array
    {
        $resolved = [];

        foreach ($aliasGroups as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($headerMap[$alias])) {
                    $resolved[$key] = $headerMap[$alias];
                    break;
                }
            }
        }

        return $resolved;
    }
}
