<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Menghitung slot waktu yang benar-benar tersedia untuk sebuah layanan pada
 * tanggal tertentu. Action ini murni (pure): hanya menerima parameter dan
 * mengembalikan array, tanpa menyentuh session/request, sehingga mudah diuji.
 */
class GetAvailableSlots
{
    /**
     * @return array<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, staff_member_ids: array<int, int>}>
     */
    public function execute(Service $service, string $date, ?StaffMember $onlyStaff = null): array
    {
        $business = $service->business;
        $tz = $business->timezone;
        $day = CarbonImmutable::parse($date, $tz)->startOfDay();
        $dayOfWeek = $day->dayOfWeek;
        $now = CarbonImmutable::now($tz);

        $businessHours = WorkingHour::query()
            ->where('business_id', $business->id)
            ->whereNull('staff_member_id')
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $businessHours || $businessHours->is_closed || ! $businessHours->opens_at || ! $businessHours->closes_at) {
            return [];
        }

        if ($this->isFullyClosedByTimeOff($business->id, null, $day, $tz)) {
            return [];
        }

        $businessOpen = $day->setTimeFromTimeString($businessHours->opens_at);
        $businessClose = $day->setTimeFromTimeString($businessHours->closes_at);

        $staffMembers = $onlyStaff
            ? collect([$onlyStaff])
            : $service->staffMembers()->where('staff_members.is_active', true)->get();

        if ($staffMembers->isEmpty()) {
            return [];
        }

        $slotInterval = $business->slot_interval;
        $duration = $service->duration_minutes;
        $earliestBookable = $now->addMinutes(15);

        /** @var Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, staff_member_id: int}> $found */
        $found = collect();

        foreach ($staffMembers as $staff) {
            $staffHours = WorkingHour::query()
                ->where('business_id', $business->id)
                ->where('staff_member_id', $staff->id)
                ->where('day_of_week', $dayOfWeek)
                ->first();

            if (! $staffHours || $staffHours->is_closed || ! $staffHours->opens_at || ! $staffHours->closes_at) {
                continue;
            }

            if ($this->isFullyClosedByTimeOff($business->id, $staff->id, $day, $tz)) {
                continue;
            }

            $staffOpen = $day->setTimeFromTimeString($staffHours->opens_at);
            $staffClose = $day->setTimeFromTimeString($staffHours->closes_at);

            $windowStart = $businessOpen->greaterThan($staffOpen) ? $businessOpen : $staffOpen;
            $windowEnd = $businessClose->lessThan($staffClose) ? $businessClose : $staffClose;

            if ($windowStart->greaterThanOrEqualTo($windowEnd)) {
                continue;
            }

            $timeOffRanges = TimeOff::query()
                ->where('business_id', $business->id)
                ->where(function ($q) use ($staff) {
                    $q->whereNull('staff_member_id')->orWhere('staff_member_id', $staff->id);
                })
                ->where('starts_at', '<', $day->addDay()->utc())
                ->where('ends_at', '>', $day->utc())
                ->get(['starts_at', 'ends_at'])
                ->map(fn ($t) => [
                    'starts_at' => CarbonImmutable::parse($t->starts_at, $tz),
                    'ends_at' => CarbonImmutable::parse($t->ends_at, $tz),
                ]);

            $blockedRanges = Booking::query()
                ->with('service:id,buffer_minutes')
                ->where('staff_member_id', $staff->id)
                ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
                ->where('starts_at', '<', $day->addDay()->utc())
                ->where('ends_at', '>', $day->subDay()->utc())
                ->get(['id', 'starts_at', 'ends_at', 'service_id'])
                ->map(fn ($b) => [
                    'starts_at' => CarbonImmutable::parse($b->starts_at, $tz),
                    'blocked_until' => CarbonImmutable::parse($b->ends_at, $tz)
                        ->addMinutes($b->service?->buffer_minutes ?? 0),
                ]);

            $slotStart = $windowStart;

            while (true) {
                $slotEnd = $slotStart->addMinutes($duration);

                if ($slotEnd->greaterThan($windowEnd)) {
                    break;
                }

                if ($slotStart->greaterThanOrEqualTo($earliestBookable)
                    && ! $this->overlapsTimeOff($slotStart, $slotEnd, $timeOffRanges)
                    && ! $this->overlapsBooking($slotStart, $slotEnd, $blockedRanges)) {
                    $found->push([
                        'starts_at' => $slotStart,
                        'ends_at' => $slotEnd,
                        'staff_member_id' => $staff->id,
                    ]);
                }

                $slotStart = $slotStart->addMinutes($slotInterval);
            }
        }

        return $found
            ->groupBy(fn ($slot) => $slot['starts_at']->toIso8601String())
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'starts_at' => $first['starts_at'],
                    'ends_at' => $first['ends_at'],
                    'staff_member_ids' => $group->pluck('staff_member_id')->unique()->values()->all(),
                ];
            })
            ->sortBy(fn ($slot) => $slot['starts_at']->timestamp)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>  $ranges
     */
    private function overlapsTimeOff(CarbonImmutable $start, CarbonImmutable $end, Collection $ranges): bool
    {
        return $ranges->contains(
            fn ($range) => $start->lessThan($range['ends_at']) && $end->greaterThan($range['starts_at'])
        );
    }

    /**
     * @param  Collection<int, array{starts_at: CarbonImmutable, blocked_until: CarbonImmutable}>  $bookings
     */
    private function overlapsBooking(CarbonImmutable $start, CarbonImmutable $end, Collection $bookings): bool
    {
        return $bookings->contains(
            fn ($booking) => $start->lessThan($booking['blocked_until']) && $end->greaterThan($booking['starts_at'])
        );
    }

    private function isFullyClosedByTimeOff(int $businessId, ?int $staffMemberId, CarbonImmutable $day, string $tz): bool
    {
        $dayStart = $day->utc();
        $dayEnd = $day->addDay()->utc();

        return TimeOff::query()
            ->where('business_id', $businessId)
            ->where('staff_member_id', $staffMemberId)
            ->where('starts_at', '<=', $dayStart)
            ->where('ends_at', '>=', $dayEnd)
            ->exists();
    }
}
