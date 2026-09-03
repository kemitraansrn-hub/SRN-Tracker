<?php

namespace App\Support;

class Terbilang
{
    private static array $angka = [
        '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan',
        'Sepuluh', 'Sebelas',
    ];

    public static function terbilang(int $angka): string
    {
        if ($angka === 0) {
            return 'Nol';
        }

        $prefix = $angka < 0 ? 'Minus ' : '';

        return trim($prefix . self::convert(abs($angka)));
    }

    private static function convert(int $n): string
    {
        if ($n < 12) {
            return self::$angka[$n];
        }

        if ($n < 20) {
            return self::convert($n - 10) . ' Belas';
        }

        if ($n < 100) {
            $sisa = $n % 10;

            return self::convert(intdiv($n, 10)) . ' Puluh' . ($sisa !== 0 ? ' ' . self::convert($sisa) : '');
        }

        if ($n < 200) {
            $sisa = $n % 100;

            return 'Seratus' . ($sisa !== 0 ? ' ' . self::convert($sisa) : '');
        }

        if ($n < 1000) {
            $sisa = $n % 100;

            return self::convert(intdiv($n, 100)) . ' Ratus' . ($sisa !== 0 ? ' ' . self::convert($sisa) : '');
        }

        // "Seribu", not "Satu Ribu", only for 1000-1999
        if ($n < 2000) {
            $sisa = $n % 1000;

            return 'Seribu' . ($sisa !== 0 ? ' ' . self::convert($sisa) : '');
        }

        if ($n < 1000000) {
            $sisa = $n % 1000;

            return self::convert(intdiv($n, 1000)) . ' Ribu' . ($sisa !== 0 ? ' ' . self::convert($sisa) : '');
        }

        if ($n < 1000000000) {
            $sisa = $n % 1000000;

            return self::convert(intdiv($n, 1000000)) . ' Juta' . ($sisa !== 0 ? ' ' . self::convert($sisa) : '');
        }

        if ($n < 1000000000000) {
            $sisa = $n % 1000000000;

            return self::convert(intdiv($n, 1000000000)) . ' Miliar' . ($sisa !== 0 ? ' ' . self::convert($sisa) : '');
        }

        $sisa = $n % 1000000000000;

        return self::convert(intdiv($n, 1000000000000)) . ' Triliun' . ($sisa !== 0 ? ' ' . self::convert($sisa) : '');
    }
}
