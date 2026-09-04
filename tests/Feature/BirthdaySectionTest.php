<?php

use App\Models\User;
use App\Services\BirthdayService;
use App\View\Components\LandingHomeBirthday;

test('birthday service generates dynamic phrases and tags for celebrants', function () {
    $celebrants = [
        ['name' => 'Ana López'],
        ['name' => 'Roberto Gómez'],
    ];

    $dynamic = BirthdayService::makeDynamic($celebrants);

    expect($dynamic)->toHaveCount(2)
        ->and($dynamic[0]['name'])->toBe('Ana López')
        ->and($dynamic[0]['message'])->toBeString()->not->toBeEmpty()
        ->and($dynamic[0]['tags'])->toBeArray()->toHaveCount(3)
        ->and($dynamic[1]['name'])->toBe('Roberto Gómez')
        ->and($dynamic[1]['message'])->toBeString()->not->toBeEmpty()
        ->and($dynamic[1]['tags'])->toBeArray()->toHaveCount(3)
        // Ensure phrases are distinct across celebrants
        ->and($dynamic[0]['message'])->not->toBe($dynamic[1]['message']);
});

test('birthday service preserves custom message and tags when provided', function () {
    $celebrants = [
        [
            'name' => 'Elena Cruz',
            'message' => '¡Felicidades Elena en tu día!',
            'tags' => ['Alegría 🥳', 'Paz 🕊️'],
        ],
    ];

    $dynamic = BirthdayService::makeDynamic($celebrants);

    expect($dynamic[0]['message'])->toBe('¡Felicidades Elena en tu día!')
        ->and($dynamic[0]['tags'])->toBe(['Alegría 🥳', 'Paz 🕊️']);
});

test('birthday service fetches only celebrants of the day from database', function () {
    $today = now();

    $todayCelebrant = User::factory()->create([
        'name' => 'Teresa',
        'surnames' => 'Méndez',
        'is_active' => true,
        'birth_date' => $today->copy()->subYears(30)->format('Y-m-d'),
    ]);

    $futureCelebrant = User::factory()->create([
        'name' => 'Armando',
        'surnames' => 'Sánchez',
        'is_active' => true,
        'birth_date' => $today->copy()->subYears(28)->addDays(5)->format('Y-m-d'),
    ]);

    $celebrants = BirthdayService::getCelebrants(5);

    $celebrantNames = collect($celebrants)->pluck('name')->all();

    expect($celebrantNames)->toContain($todayCelebrant->full_name)
        ->and($celebrantNames)->not->toContain($futureCelebrant->full_name);
});

test('home page renders birthday section when there are celebrants today', function () {
    $today = now();

    User::factory()->create([
        'name' => 'Camila',
        'surnames' => 'Reyes',
        'is_active' => true,
        'birth_date' => $today->copy()->subYears(26)->format('Y-m-d'),
    ]);

    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('Cumpleañeros de Hoy')
        ->assertSee('Camila Reyes')
        ->assertSee('avatar')
        ->assertSee('¡Cumpleaños Hoy!');
});

test('landing home birthday component can be rendered with custom celebrants or limit', function () {
    $view = $this->component(LandingHomeBirthday::class, [
        'limit' => 2,
        'celebrants' => [
            ['name' => 'Marcos Vega', 'message' => '¡Feliz día Marcos!', 'tags' => ['Salud 🌿']],
        ],
    ]);

    $view->assertSee('Marcos Vega')
        ->assertSee('¡Feliz día Marcos!')
        ->assertSee('Salud 🌿');
});
