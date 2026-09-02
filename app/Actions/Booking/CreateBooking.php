<?php

namespace App\Actions\Booking;

use App\Exceptions\BookingUnavailableException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat booking baru dengan penguncian transaksi agar dua orang tidak
 * pernah mendapat slot staf yang sama, walau menekan tombol pada detik
 * yang sama. Jangan pernah percaya hasil GetAvailableSlots yang dihitung
 * sebelum transaksi ini — semuanya dicek ulang di dalam sini.
 */
class CreateBooking
{
    public function execute(
        Service $service,
        CarbonImmutable $startsAt,
        string $customerName,
        string $customerPhone,
        string $source = Booking::SOURCE_ONLINE,
        ?StaffMember $staffMember = null,
        ?int $createdBy = null,
        ?string $note = null,
    ): Booking {
        $business = $service->business;
        $endsAt = $startsAt->addMinutes($service->duration_minutes);

        return DB::transaction(function () use (
            $business, $service, $startsAt, $endsAt, $customerName, $customerPhone,
            $source, $staffMember, $createdBy, $note,
        ) {
            $assignedStaffId = null;

            if ($service->requires_staff) {
                $candidates = $staffMember
                    ? collect([$staffMember])
                    : $service->staffMembers()->where('staff_members.is_active', true)->get();

                foreach ($candidates as $candidate) {
                    if (! $this->isWithinWorkingWindow($business, $candidate, $startsAt, $endsAt)) {
                        continue;
                    }

                    // Kunci booking staf ini pada rentang yang tumpang tindih (termasuk buffer
                    // layanan sebelumnya) agar percobaan paralel diserialisasi di sini.
                    $conflict = Booking::query()
                        ->where('staff_member_id', $candidate->id)
                        ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                        ->where('starts_at', '<', $endsAt)
                        ->where('ends_at', '>', $startsAt->subMinutes($service->buffer_minutes))
                        ->lockForUpdate()
                        ->exists();

                    if (! $conflict) {
                        $assignedStaffId = $candidate->id;
                        break;
                    }
                }

                if ($assignedStaffId === null) {
                    throw BookingUnavailableException::noStaffAvailable();
                }
            }

            $tz = $business->timezone;
            $queueDate = $startsAt->setTimezone($tz)->toDateString();
            $queueNumber = (new AssignQueueNumber)->execute($business, CarbonImmutable::parse($queueDate, $tz));

            try {
                $booking = Booking::create([
                    'business_id' => $business->id,
                    'service_id' => $service->id,
                    'staff_member_id' => $assignedStaffId,
                    'customer_name' => $customerName,
                    'customer_phone' => PhoneNumber::normalize($customerPhone),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'source' => $source,
                    'status' => Booking::STATUS_CONFIRMED,
                    'queue_number' => $queueNumber,
                    'queue_date' => $queueDate,
                    'code' => $this->generateUniqueCode(),
                    'public_token' => Str::random(40),
                    'note' => $note,
                    'created_by' => $createdBy,
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    throw BookingUnavailableException::slotTaken();
                }

                throw $e;
            }

            AuditLog::create([
                'actor_type' => $createdBy ? AuditLog::ACTOR_STAFF : AuditLog::ACTOR_CUSTOMER,
                'actor_id' => $createdBy,
                'booking_id' => $booking->id,
                'from_status' => null,
                'to_status' => Booking::STATUS_CONFIRMED,
            ]);

            return $booking;
        });
    }

    private function isWithinWorkingWindow(
        Business $business,
        StaffMember $staff,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): bool {
        $tz = $business->timezone;
        $localStart = $startsAt->setTimezone($tz);
        $localEnd = $endsAt->setTimezone($tz);
        $dayOfWeek = $localStart->dayOfWeek;

        $businessHours = WorkingHour::query()
            ->where('business_id', $business->id)
            ->whereNull('staff_member_id')
            ->where('day_of_week', $dayOfWeek)
            ->first();

        $staffHours = WorkingHour::query()
            ->where('business_id', $business->id)
            ->where('staff_member_id', $staff->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $businessHours || $businessHours->is_closed || ! $businessHours->opens_at || ! $businessHours->closes_at) {
            return false;
        }

        if (! $staffHours || $staffHours->is_closed || ! $staffHours->opens_at || ! $staffHours->closes_at) {
            return false;
        }

        $day = $localStart->startOfDay();
        $businessOpen = $day->setTimeFromTimeString($businessHours->opens_at);
        $businessClose = $day->setTimeFromTimeString($businessHours->closes_at);
        $staffOpen = $day->setTimeFromTimeString($staffHours->opens_at);
        $staffClose = $day->setTimeFromTimeString($staffHours->closes_at);

        $windowStart = $businessOpen->greaterThan($staffOpen) ? $businessOpen : $staffOpen;
        $windowEnd = $businessClose->lessThan($staffClose) ? $businessClose : $staffClose;

        if ($localStart->lessThan($windowStart) || $localEnd->greaterThan($windowEnd)) {
            return false;
        }

        $onTimeOff = TimeOff::query()
            ->where('business_id', $business->id)
            ->where(function ($q) use ($staff) {
                $q->whereNull('staff_member_id')->orWhere('staff_member_id', $staff->id);
            })
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();

        return ! $onTimeOff;
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Booking::where('code', $code)->exists());

        return $code;
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        // SQLite: "UNIQUE constraint failed". MySQL: SQLSTATE 23000, errno 1062.
        return $e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }
}
