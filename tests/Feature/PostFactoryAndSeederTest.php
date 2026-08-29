<?php

use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PostSeeder;

test('post factory creates a post correctly', function () {
    $post = Post::factory()->create();

    expect($post)->toBeInstanceOf(Post::class);
    expect($post->author)->toBeInstanceOf(User::class);
    expect($post->title)->toBeString();
    expect($post->slug)->toBeString();
    expect($post->type)->toBeInstanceOf(PostType::class);
    expect($post->content)->toBeString();
});

test('post seeder creates 50 posts', function () {
    $this->seed(PostSeeder::class);

    expect(Post::count())->toBe(50);
});

test('post isExpired method works correctly', function () {
    $expiredPost = Post::factory()->create([
        'expires_at' => now()->subDay(),
    ]);

    $activePost = Post::factory()->create([
        'expires_at' => now()->addDay(),
    ]);

    $neverExpiresPost = Post::factory()->create([
        'expires_at' => null,
    ]);

    expect($expiredPost->isExpired())->toBeTrue();
    expect($activePost->isExpired())->toBeFalse();
    expect($neverExpiresPost->isExpired())->toBeFalse();
});

test('home page renders carousel with posts', function () {
    Post::query()->delete();

    $posts = Post::factory()->count(3)->create([
        'expires_at' => null,
    ]);

    $response = $this->get('/');

    $response->assertStatus(200);

    foreach ($posts as $post) {
        $response->assertSee($post->title);
    }
});
