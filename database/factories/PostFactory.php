<?php

namespace Database\Factories;

use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'author_id' => User::factory(),
            'type' => fake()->randomElement(PostType::cases()),
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => fake()->paragraphs(3, true),
            'thumbnail' => fake()->optional()->imageUrl(),
            'expires_at' => fake()->optional()->dateTimeBetween('+1 week', '+1 year'),
        ];
    }
}
