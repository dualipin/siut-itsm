<?php

namespace Database\Factories;

use App\Models\RequestType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RequestType>
 */
class RequestTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'icon' => 'heroicon-o-document-text',
            'requires_attachment' => fake()->boolean(40),
            'attachment_instructions' => 'Por favor adjunte comprobante o cotización en formato PDF o imagen.',
            'custom_fields' => null,
            'max_per_user_per_year' => fake()->optional()->numberBetween(1, 3),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
