<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeOff>
 */
class TimeOffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+2 weeks');

        return [
            'business_id' => Business::factory(),
            'staff_member_id' => null,
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+1 day'),
            'reason' => fake()->randomElement(['Cuti tahunan', 'Sakit', 'Izin keluarga']),
        ];
    }
}
