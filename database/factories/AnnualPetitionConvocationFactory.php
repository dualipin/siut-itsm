<?php

namespace Database\Factories;

use App\Models\AnnualPetitionConvocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnualPetitionConvocation>
 */
class AnnualPetitionConvocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->optional()->words(3, true),
            'sort_order' => 0,
        ];
    }
}
