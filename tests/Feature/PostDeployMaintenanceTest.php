<?php

use App\Enums\UserRole;
use App\Filament\Pages\PostDeployMaintenance;
use App\Filament\Resources\Themes\ThemeResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('guest users cannot access post-deploy page', function () {
    $this->get('/portal/post-deploy')
        ->assertRedirect('/portal/login');
});

test('non-admin users cannot access post-deploy page', function () {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
        'is_active' => true,
    ]);

    $this->actingAs($agremiado)
        ->get('/portal/post-deploy')
        ->assertForbidden();
});

test('admin can access post-deploy page and view diagnostic sections', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get('/portal/post-deploy')
        ->assertSuccessful()
        ->assertSee('Mantenimiento del Sistema y Despliegue')
        ->assertSee('Almacenamiento')
        ->assertSee('Base de Datos');
});

test('navigation group is configured correctly for system configuration category', function () {
    expect(ThemeResource::getNavigationGroup())->toBe('Configuración del Sistema')
        ->and(PostDeployMaintenance::getNavigationGroup())->toBe('Configuración del Sistema')
        ->and(PostDeployMaintenance::getNavigationLabel())->toBe('Mantenimiento del Sistema')
        ->and(ThemeResource::getNavigationLabel())->toBe('Apariencia');
});

test('admin can repair storage link and test storage access from livewire', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    Livewire::test(PostDeployMaintenance::class)
        ->call('repairStorageLink')
        ->assertHasNoErrors()
        ->call('testStorageAccess')
        ->assertHasNoErrors()
        ->assertSet('terminalLogs', function ($logs) {
            return count($logs) >= 2;
        });
});

test('admin can run migrate status, optimize clear and clear terminal logs', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    Livewire::test(PostDeployMaintenance::class)
        ->call('runMigrateStatus')
        ->call('runOptimizeClear')
        ->assertHasNoErrors()
        ->assertSet('terminalLogs', function ($logs) {
            return count($logs) >= 2;
        })
        ->call('clearTerminalLogs')
        ->assertSet('terminalLogs', []);
});

test('admin can run full deploy sequence successfully', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    Livewire::test(PostDeployMaintenance::class)
        ->call('runFullDeploy')
        ->assertHasNoErrors()
        ->assertSet('terminalLogs', function ($logs) {
            return count($logs) >= 5;
        });
});

test('storage fallback route serves public files when requested without physical symlink', function () {
    Storage::disk('public')->put('profile-photos/test-avatar.jpg', 'avatar-binary-content');

    $response = $this->get('/storage/profile-photos/test-avatar.jpg');

    $response->assertSuccessful();
    expect($response->streamedContent())->toBe('avatar-binary-content');

    Storage::disk('public')->delete('profile-photos/test-avatar.jpg');
});

test('storage fallback returns 404 for non-existent files or invalid traversal', function () {
    $this->get('/storage/non-existent-image.jpg')
        ->assertNotFound();

    $this->get('/storage/../.env')
        ->assertNotFound();
});
