<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
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
            'name' => fake()->randomElement([
                'Scaling Gigi', 'Tambal Gigi', 'Cabut Gigi', 'Konsultasi', 'Pemutihan Gigi', 'Pasang Behel',
            ]),
            'description' => fake()->optional()->sentence(),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60]),
            'buffer_minutes' => fake()->randomElement([0, 5, 10]),
            'price' => fake()->randomElement([50000, 75000, 100000, 150000, 250000]),
            'requires_staff' => true,
            'is_active' => true,
        ];
    }
}
