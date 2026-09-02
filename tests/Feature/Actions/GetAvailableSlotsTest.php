<?php

use App\Actions\Booking\GetAvailableSlots;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;

/**
 * Selasa, 2026-09-08, dipakai sebagai "hari kerja normal" di semua test
 * kecuali disebutkan lain. day_of_week Selasa = 2.
 */
const TEST_DATE = '2026-09-08';

const TEST_DOW = 2;

function makeBusiness(array $attrs = []): Business
{
    return Business::factory()->create(array_merge([
        'timezone' => 'Asia/Jakarta',
        'slot_interval' => 15,
    ], $attrs));
}

function makeBusinessHours(Business $business, int $dow, ?string $opens = '09:00', ?string $closes = '17:00', bool $closed = false): WorkingHour
{
    return WorkingHour::factory()->create([
        'business_id' => $business->id,
        'staff_member_id' => null,
        'day_of_week' => $dow,
        'opens_at' => $opens,
        'closes_at' => $closes,
        'is_closed' => $closed,
    ]);
}

function makeStaffHours(Business $business, StaffMember $staff, int $dow, ?string $opens = '09:00', ?string $closes = '17:00', bool $closed = false): WorkingHour
{
    return WorkingHour::factory()->create([
        'business_id' => $business->id,
        'staff_member_id' => $staff->id,
        'day_of_week' => $dow,
        'opens_at' => $opens,
        'closes_at' => $closes,
        'is_closed' => $closed,
    ]);
}

test('slot kosong jika hari itu tutup (is_closed)', function () {
    $business = makeBusiness();
    makeBusinessHours($business, TEST_DOW, closed: true);
    $service = Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 30]);
    $staff = StaffMember::factory()->create(['business_id' => $business->id]);
    $staff->services()->attach($service);
    makeStaffHours($business, $staff, TEST_DOW);

    $slots = (new GetAvailableSlots)->execute($service, TEST_DATE);

    expect($slots)->toBeEmpty();
});

test('slot kosong jika tidak ada jam operasional untuk hari itu', function () {
    $business = makeBusiness();
    // Tidak membuat working_hours sama sekali untuk hari ini -> dianggap tutup.
    $service = Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 30]);
    $staff = StaffMember::factory()->create(['business_id' => $business->id]);
    $staff->services()->attach($service);
    makeStaffHours($business, $staff, TEST_DOW);

    $slots = (new GetAvailableSlots)->execute($service, TEST_DATE);

    expect($slots)->toBeEmpty();
});

test('slot kosong pada hari libur (time off seluruh usaha)', function () {
    $business = makeBusiness();
    makeBusinessHours($business, TEST_DOW);
    $service = Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 30]);
    $staff = StaffMember::factory()->create(['business_id' => $business->id]);
    $staff->services()->attach($service);
    makeStaffHours($business, $staff, TEST_DOW);

    TimeOff::factory()->create([
        'business_id' => $business->id,
        'staff_member_id' => null,
        'starts_at' => CarbonImmutable::parse(TEST_DATE, 'Asia/Jakarta')->startOfDay(),
        'ends_at' => CarbonImmutable::parse(TEST_DATE, 'Asia/Jakarta')->addDay()->startOfDay(),
        'reason' => 'Libur nasional',
    ]);

    $slots = (new GetAvailableSlots)->execute($service, TEST_DATE);

    expect($slots)->toBeEmpty();
});

test('slot yang bertabrakan dengan booking yang ada tidak muncul', function () {
    $business = makeBusiness();
    makeBusinessHours($business, TEST_DOW);
    $service = Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 30, 'buffer_minutes' => 0]);
    $staff = StaffMember::factory()->create(['business_id' => $business->id]);
    $staff->services()->attach($service);
    makeStaffHours($business, $staff, TEST_DOW);

    $bookedStart = CarbonImmutable::parse(TEST_DATE.' 10:00', 'Asia/Jakarta');

    Booking::factory()->create([
        'business_id' => $business->id,
        'service_id' => $service->id,
        'staff_member_id' => $staff->id,
        'starts_at' => $bookedStart->utc(),
        'ends_at' => $bookedStart->addMinutes(30)->utc(),
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    $slots = (new GetAvailableSlots)->execute($service, TEST_DATE);

    $times = collect($slots)->map(fn ($s) => $s['starts_at']->format('H:i'));

    expect($times)->not->toContain('10:00')
        ->and($times)->toContain('09:30') // 09:30-10:00, pas berakhir saat booking mulai
        ->and($times)->toContain('10:30');
});

test('buffer setelah layanan memblokir slot berikutnya', function () {
    $business = makeBusiness();
    makeBusinessHours($business, TEST_DOW);
    $service = Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 30, 'buffer_minutes' => 15]);
    $staff = StaffMember::factory()->create(['business_id' => $business->id]);
    $staff->services()->attach($service);
    makeStaffHours($business, $staff, TEST_DOW);

    $bookedStart = CarbonImmutable::parse(TEST_DATE.' 10:00', 'Asia/Jakarta');

    Booking::factory()->create([
        'business_id' => $business->id,
        'service_id' => $service->id,
        'staff_member_id' => $staff->id,
        'starts_at' => $bookedStart->utc(),
        'ends_at' => $bookedStart->addMinutes(30)->utc(),
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    $slots = (new GetAvailableSlots)->execute($service, TEST_DATE);
    $times = collect($slots)->map(fn ($s) => $s['starts_at']->format('H:i'));

    // Booking 10:00-10:30 + buffer 15 menit -> terblokir sampai 10:45.
    expect($times)->not->toContain('10:00')
        ->not->toContain('10:15')
        ->not->toContain('10:30')
        ->toContain('10:45');
});

test('slot di masa lalu dan kurang dari 15 menit dari sekarang tidak muncul', function () {
    $business = makeBusiness();
    makeBusinessHours($business, TEST_DOW);
    $service = Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 30]);
    $staff = StaffMember::factory()->create(['business_id' => $business->id]);
    $staff->services()->attach($service);
    makeStaffHours($business, $staff, TEST_DOW);

    // "Sekarang" disimulasikan jam 10:05 di hari yang sama.
    CarbonImmutable::setTestNow(CarbonImmutable::parse(TEST_DATE.' 10:05', 'Asia/Jakarta'));

    $slots = (new GetAvailableSlots)->execute($service, TEST_DATE);
    $times = collect($slots)->map(fn ($s) => $s['starts_at']->format('H:i'));

    expect($times)->not->toContain('09:00')
        ->not->toContain('09:45')
        ->not->toContain('10:00') // kurang dari 15 menit dari sekarang (10:05)
        ->not->toContain('10:15') // 10 menit dari sekarang, masih < 15 menit
        ->toContain('10:30');

    CarbonImmutable::setTestNow();
});

test('cuti staf menghapus slot staf itu saja, bukan slot staf lain', function () {
    $business = makeBusiness();
    makeBusinessHours($business, TEST_DOW);
    $service = Service::factory()->create(['business_id' => $business->id, 'duration_minutes' => 30]);

    $staffA = StaffMember::factory()->create(['business_id' => $business->id, 'name' => 'Staf A']);
    $staffB = StaffMember::factory()->create(['business_id' => $business->id, 'name' => 'Staf B']);
    $staffA->services()->attach($service);
    $staffB->services()->attach($service);
    makeStaffHours($business, $staffA, TEST_DOW);
    makeStaffHours($business, $staffB, TEST_DOW);

    TimeOff::factory()->create([
        'business_id' => $business->id,
        'staff_member_id' => $staffA->id,
        'starts_at' => CarbonImmutable::parse(TEST_DATE, 'Asia/Jakarta')->startOfDay(),
        'ends_at' => CarbonImmutable::parse(TEST_DATE, 'Asia/Jakarta')->addDay()->startOfDay(),
        'reason' => 'Cuti',
    ]);

    $allSlots = (new GetAvailableSlots)->execute($service, TEST_DATE);
    expect($allSlots)->not->toBeEmpty();
    foreach ($allSlots as $slot) {
        expect($slot['staff_member_ids'])->not->toContain($staffA->id)
            ->toContain($staffB->id);
    }

    $slotsForStaffA = (new GetAvailableSlots)->execute($service, TEST_DATE, $staffA);
    expect($slotsForStaffA)->toBeEmpty();

    $slotsForStaffB = (new GetAvailableSlots)->execute($service, TEST_DATE, $staffB);
    expect($slotsForStaffB)->not->toBeEmpty();
});
