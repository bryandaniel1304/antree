<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\Business;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Memberi nomor antrean berurutan per usaha per hari. Harus dipanggil di
 * dalam DB::transaction() milik pemanggil, karena mengunci baris booking
 * hari itu untuk mencegah dua booking mendapat nomor yang sama.
 */
class AssignQueueNumber
{
    public function execute(Business $business, CarbonInterface $queueDate): int
    {
        $last = Booking::query()
            ->where('business_id', $business->id)
            ->whereDate('queue_date', $queueDate->toDateString())
            ->lockForUpdate()
            ->max('queue_number');

        return ((int) $last) + 1;
    }
}
