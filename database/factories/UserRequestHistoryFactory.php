<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\User;
use App\Models\UserRequest;
use App\Models\UserRequestHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserRequestHistory>
 */
class UserRequestHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_request_id' => UserRequest::factory(),
            'user_id' => User::factory(),
            'from_status' => RequestStatus::Pending,
            'to_status' => RequestStatus::UnderReview,
            'notes' => fake()->sentence(),
        ];
    }
}
