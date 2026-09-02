<?php

namespace App\Exceptions;

use Exception;

/**
 * Dilempar saat slot yang diminta ternyata sudah tidak tersedia lagi —
 * baik karena tabrakan yang terdeteksi di dalam transaksi, maupun karena
 * unique index database menolak insert-nya (jaring pengaman terakhir).
 */
class BookingUnavailableException extends Exception
{
    public static function slotTaken(): self
    {
        return new self('Slot ini baru saja dipesan orang lain. Silakan pilih slot lain.');
    }

    public static function noStaffAvailable(): self
    {
        return new self('Tidak ada staf yang tersedia untuk slot ini. Silakan pilih slot lain.');
    }
}
