<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_type' => AuditLog::ACTOR_SYSTEM,
            'actor_id' => null,
            'booking_id' => Booking::factory(),
            'from_status' => null,
            'to_status' => Booking::STATUS_PENDING,
            'created_at' => now(),
        ];
    }
}
