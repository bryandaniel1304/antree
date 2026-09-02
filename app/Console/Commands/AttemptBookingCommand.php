<?php

namespace App\Console\Commands;

use App\Actions\Booking\CreateBooking;
use App\Exceptions\BookingUnavailableException;
use App\Models\Service;
use App\Models\StaffMember;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Perintah bantu untuk pengujian: mencoba membuat satu booking lalu keluar.
 * Dipakai oleh test konkurensi untuk mensimulasikan dua proses independen
 * yang menekan tombol booking pada waktu yang (nyaris) bersamaan.
 */
class AttemptBookingCommand extends Command
{
    protected $signature = 'booking:attempt
        {service_id} {staff_id} {starts_at} {--name=Pelanggan} {--phone=081234567890}';

    protected $description = 'Coba buat satu booking (dipakai untuk uji konkurensi).';

    public function handle(): int
    {
        $service = Service::findOrFail((int) $this->argument('service_id'));
        $staff = StaffMember::findOrFail((int) $this->argument('staff_id'));
        $startsAt = CarbonImmutable::parse($this->argument('starts_at'), $service->business->timezone);

        try {
            $booking = (new CreateBooking)->execute(
                service: $service,
                startsAt: $startsAt,
                customerName: $this->option('name'),
                customerPhone: $this->option('phone'),
                staffMember: $staff,
            );

            $this->line('OK:'.$booking->id);

            return self::SUCCESS;
        } catch (BookingUnavailableException $e) {
            $this->line('FAIL:'.$e->getMessage());

            return self::FAILURE;
        }
    }
}
