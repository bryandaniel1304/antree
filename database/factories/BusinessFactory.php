<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Business>
 */
class BusinessFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'address' => fake()->address(),
            'phone' => '628'.fake()->numerify('##########'),
            'timezone' => 'Asia/Jakarta',
            'slot_interval' => 15,
            'max_days_ahead' => 30,
            'settings' => [],
        ];
    }
}
