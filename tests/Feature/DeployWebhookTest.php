<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Config;

test('deploy webhook returns 403 when token is missing', function () {
    Config::set('app.deploy_secret', 'secret-deploy-token-12345');

    $this->postJson('/api/deploy-webhook')
        ->assertStatus(403);
});

test('deploy webhook returns 403 when token is invalid', function () {
    Config::set('app.deploy_secret', 'secret-deploy-token-12345');

    $this->postJson('/api/deploy-webhook', [], [
        'X-Deploy-Token' => 'invalid-token',
    ])->assertStatus(403);
});

test('deploy webhook runs maintenance sequence and returns json when token is valid', function () {
    Config::set('app.deploy_secret', 'secret-deploy-token-12345');

    $response = $this->postJson('/api/deploy-webhook', [], [
        'X-Deploy-Token' => 'secret-deploy-token-12345',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
        ])
        ->assertJsonStructure([
            'success',
            'duration_seconds',
            'results' => [
                'migrate',
                'optimize:clear',
                'optimize',
                'view:cache',
            ],
        ]);
});

test('guest cannot access browser maintenance clear fallback route', function () {
    $this->get('/portal/maintenance/clear')
        ->assertRedirect('/portal/login');
});

test('non admin cannot access browser maintenance clear fallback route', function () {
    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get('/portal/maintenance/clear')
        ->assertStatus(403);
});

test('admin can access browser maintenance clear fallback route and is redirected to portal', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get('/portal/maintenance/clear')
        ->assertRedirect('/portal')
        ->assertSessionHas('status', 'Caché optimizada limpiada correctamente.');
});
