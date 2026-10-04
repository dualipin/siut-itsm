<?php

namespace Database\Factories;

use App\Models\AnnualPetition;
use App\Models\PetitionRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetitionRequest>
 */
class PetitionRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'annual_petition_id' => AnnualPetition::factory(),
            'curp' => strtoupper(fake()->bothify('????######??######??##')),
            'agremiado_name' => fake()->name(),
            'proposal' => fake()->paragraph(),
        ];
    }
}
