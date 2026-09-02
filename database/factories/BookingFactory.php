<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Service;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = fake()->dateTimeBetween('-30 days', '+7 days');
        $duration = fake()->randomElement([15, 30, 45, 60]);
        $ends = (clone $starts)->modify("+{$duration} minutes");

        return [
            'business_id' => Business::factory(),
            'service_id' => Service::factory(),
            'staff_member_id' => StaffMember::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => '628'.fake()->numerify('##########'),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'source' => fake()->randomElement([
                Booking::SOURCE_ONLINE, Booking::SOURCE_WALK_IN, Booking::SOURCE_PHONE,
            ]),
            'status' => Booking::STATUS_CONFIRMED,
            'queue_number' => fake()->numberBetween(1, 40),
            'queue_date' => $starts->format('Y-m-d'),
            'code' => strtoupper(Str::random(6)),
            'public_token' => Str::random(40),
            'created_by' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_COMPLETED,
            'completed_at' => $attributes['ends_at'],
        ]);
    }

    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_NO_SHOW,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }
}
