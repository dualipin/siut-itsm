<?php

namespace Database\Factories;

use App\Models\AnnualPetition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnualPetition>
 */
class AnnualPetitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2020, 2035);

        return [
            'year' => $year,
            'deadline' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'deadline' => fake()->dateTimeBetween('-6 months', '-1 day')->format('Y-m-d'),
        ]);
    }
}
