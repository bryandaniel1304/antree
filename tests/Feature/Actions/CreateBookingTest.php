<?php

use App\Actions\Booking\CreateBooking;
use App\Exceptions\BookingUnavailableException;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;

function setUpBookableBusiness(): array
{
    $business = Business::factory()->create(['timezone' => 'Asia/Jakarta', 'slot_interval' => 15]);
    $service = Service::factory()->create([
        'business_id' => $business->id,
        'duration_minutes' => 30,
        'buffer_minutes' => 0,
    ]);
    $staff = StaffMember::factory()->create(['business_id' => $business->id]);
    $staff->services()->attach($service);

    // Selasa (2026-09-08) dow=2, buka 09:00-17:00 untuk usaha & staf.
    foreach ([null, $staff->id] as $staffMemberId) {
        WorkingHour::factory()->create([
            'business_id' => $business->id,
            'staff_member_id' => $staffMemberId,
            'day_of_week' => 2,
            'opens_at' => '09:00',
            'closes_at' => '17:00',
            'is_closed' => false,
        ]);
    }

    return [$business, $service, $staff];
}

test('booking berhasil dibuat dan mendapat nomor antrean', function () {
    [$business, $service, $staff] = setUpBookableBusiness();

    $booking = (new CreateBooking)->execute(
        service: $service,
        startsAt: CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Jakarta'),
        customerName: 'Budi',
        customerPhone: '08123456789',
        staffMember: $staff,
    );

    expect($booking->queue_number)->toBe(1)
        ->and($booking->customer_phone)->toBe('628123456789')
        ->and($booking->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($booking->code)->toHaveLength(6);
});

test('nomor antrean berurutan per hari dan mulai dari 1 lagi di hari berikutnya', function () {
    [$business, $service, $staff] = setUpBookableBusiness();

    WorkingHour::factory()->create([
        'business_id' => $business->id,
        'staff_member_id' => null,
        'day_of_week' => 3,
        'opens_at' => '09:00',
        'closes_at' => '17:00',
    ]);
    WorkingHour::factory()->create([
        'business_id' => $business->id,
        'staff_member_id' => $staff->id,
        'day_of_week' => 3,
        'opens_at' => '09:00',
        'closes_at' => '17:00',
    ]);

    $b1 = (new CreateBooking)->execute($service, CarbonImmutable::parse('2026-09-08 09:00', 'Asia/Jakarta'), 'A', '0811', staffMember: $staff);
    $b2 = (new CreateBooking)->execute($service, CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Jakarta'), 'B', '0812', staffMember: $staff);
    $b3 = (new CreateBooking)->execute($service, CarbonImmutable::parse('2026-09-09 09:00', 'Asia/Jakarta'), 'C', '0813', staffMember: $staff);

    expect($b1->queue_number)->toBe(1)
        ->and($b2->queue_number)->toBe(2)
        ->and($b3->queue_number)->toBe(1);
});

test('booking pada slot staf yang sudah terisi ditolak', function () {
    [$business, $service, $staff] = setUpBookableBusiness();

    (new CreateBooking)->execute($service, CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Jakarta'), 'A', '0811', staffMember: $staff);

    expect(fn () => (new CreateBooking)->execute($service, CarbonImmutable::parse('2026-09-08 10:00', 'Asia/Jakarta'), 'B', '0812', staffMember: $staff))
        ->toThrow(BookingUnavailableException::class);
});
