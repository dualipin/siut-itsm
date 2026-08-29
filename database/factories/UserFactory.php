<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'surnames' => fake()->lastName().' '.fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement(UserRole::cases()),
            'is_active' => true,
            'curp' => strtoupper(fake()->unique()->bothify('????######??????##')),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'photo_path' => null,
            'category' => fake()->randomElement(['Docente', 'Administrativo', 'Técnico', 'Mantenimiento', 'Directivo']),
            'nss' => fake()->unique()->numerify('###########'),
            'salary' => fake()->randomFloat(2, 5000, 50000),
            'hiring_date' => fake()->dateTimeBetween('-10 years', 'now')->format('Y-m-d'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
        ]);
    }

    /**
     * Indicate that the user is a leader.
     */
    public function lider(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Lider,
        ]);
    }

    /**
     * Indicate that the user is an agremiado.
     */
    public function agremiado(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Agremiado,
        ]);
    }

    /**
     * Indicate that the user is soft deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }

    /**
     * Indicate that the user has a profile photo.
     */
    public function withPhoto(?string $path = null): static
    {
        return $this->state(fn (array $attributes) => [
            'photo_path' => $path ?? 'users/default-avatar.png',
        ]);
    }
}
