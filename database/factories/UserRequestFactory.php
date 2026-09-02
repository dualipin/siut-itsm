<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\RequestType;
use App\Models\User;
use App\Models\UserRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRequest>
 */
class UserRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'request_type_id' => RequestType::factory(),
            'reason' => fake()->sentence(10),
            'additional_data' => null,
            'status' => RequestStatus::Pending,
            'reviewed_by' => null,
            'resolution_notes' => null,
            'reviewed_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::Approved,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'resolution_notes' => 'Aprobada conforme a los estatutos y lineamientos vigentes.',
        ]);
    }

    /**
     * Indicate that the request is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RequestStatus::Rejected,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'resolution_notes' => 'Rechazada por falta de documentación probatoria.',
        ]);
    }
}
