<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalisasi nomor HP Indonesia ke format "62xxxxxxxxxx", apa pun cara
     * pengguna mengetiknya: 08xx, +62 8xx, 62xxx, atau 8xx polos.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '620')) {
            // Kasus "+62 08xx" yang salah ketik -> buang 0 setelah 62.
            $digits = '62'.substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '62')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }
}
