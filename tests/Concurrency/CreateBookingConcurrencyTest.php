<?php

use App\Models\Business;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\WorkingHour;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

test('dua booking bersamaan untuk slot dan staf yang sama: tepat satu berhasil, satu ditolak', function () {
    // Dipakai file sqlite sungguhan (bukan :memory:) supaya dua proses PHP
    // terpisah benar-benar berbagi satu database yang sama dan konkurensinya
    // nyata — bukan dua panggilan berurutan dalam satu proses.
    $dbPath = storage_path('framework/testing/concurrency-test.sqlite');
    @mkdir(dirname($dbPath), recursive: true);
    @unlink($dbPath);
    touch($dbPath);

    config(['database.connections.sqlite.database' => $dbPath]);
    DB::purge('sqlite');

    Artisan::call('migrate', ['--database' => 'sqlite', '--force' => true]);

    $business = Business::factory()->create(['timezone' => 'Asia/Jakarta', 'slot_interval' => 15]);
    $service = Service::factory()->create([
        'business_id' => $business->id,
        'duration_minutes' => 30,
        'buffer_minutes' => 0,
    ]);
    $staff = StaffMember::factory()->create(['business_id' => $business->id]);
    $staff->services()->attach($service);

    foreach ([null, $staff->id] as $staffMemberId) {
        WorkingHour::factory()->create([
            'business_id' => $business->id,
            'staff_member_id' => $staffMemberId,
            'day_of_week' => 2, // Selasa, 2026-09-08
            'opens_at' => '09:00',
            'closes_at' => '17:00',
            'is_closed' => false,
        ]);
    }

    $php = (new PhpExecutableFinder)->find();
    $artisan = base_path('artisan');
    $startsAt = '2026-09-08 10:00';
    $env = ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $dbPath];

    $p1 = new Process([$php, $artisan, 'booking:attempt', (string) $service->id, (string) $staff->id, $startsAt], env: $env);
    $p2 = new Process([$php, $artisan, 'booking:attempt', (string) $service->id, (string) $staff->id, $startsAt], env: $env);

    $p1->start();
    $p2->start();
    $p1->wait();
    $p2->wait();

    $outputs = [$p1->getOutput().$p1->getErrorOutput(), $p2->getOutput().$p2->getErrorOutput()];
    $successes = collect($outputs)->filter(fn ($o) => str_contains($o, 'OK:'));
    $failures = collect($outputs)->filter(fn ($o) => str_contains($o, 'FAIL:'));

    expect($successes)->toHaveCount(1)
        ->and($failures)->toHaveCount(1);

    expect(DB::connection('sqlite')->table('bookings')
        ->where('staff_member_id', $staff->id)
        ->count())->toBe(1);

    @unlink($dbPath);
});
