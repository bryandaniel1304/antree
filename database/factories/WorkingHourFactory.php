<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkingHour>
 */
class WorkingHourFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'staff_member_id' => null,
            'day_of_week' => fake()->numberBetween(0, 6),
            'opens_at' => '09:00',
            'closes_at' => '17:00',
            'is_closed' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_closed' => true,
            'opens_at' => null,
            'closes_at' => null,
        ]);
    }
}
