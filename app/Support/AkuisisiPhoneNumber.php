<?php

namespace App\Support;

/** Diporting dari App\Support\PhoneNumber di PartnerAcquire CRM. */
class AkuisisiPhoneNumber
{
    /**
     * Rapikan nomor WhatsApp ke format 62xxx.
     * Contoh: "0812-3456-7890", "+62 812 3456 7890", "812 3456 7890" -> "6281234567890".
     */
    public static function normalize(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            return null;
        }

        return match (true) {
            str_starts_with($digits, '62') => $digits,
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };
    }

    /**
     * Format tampilan nomor 62xxx agar mudah dibaca: "6281266129522" -> "+62 812-6612-9522".
     */
    public static function display(?string $number): string
    {
        if (! $number || ! preg_match('/^62(\d{3})(\d{4})(\d+)$/', $number, $parts)) {
            return (string) $number;
        }

        return "+62 {$parts[1]}-{$parts[2]}-{$parts[3]}";
    }
}
