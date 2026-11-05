<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Services\RegistrationStaging;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('public');
    Storage::fake('local');
});

test('login page asks for curp instead of email', function () {
    $this->get('/portal/login')
        ->assertSuccessful()
        ->assertSee('CURP');
});

test('user can log in with an uppercase curp', function () {
    $user = User::factory()->create(['curp' => 'MELC950515HDFRRN09']);

    Livewire::test(Login::class)
        ->fillForm(['curp' => 'MELC950515HDFRRN09', 'password' => 'admin123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

test('user can log in with a lowercase curp', function () {
    $user = User::factory()->create(['curp' => 'MELC950515HDFRRN09']);

    Livewire::test(Login::class)
        ->fillForm(['curp' => ' melc950515hdfrrn09 ', 'password' => 'admin123'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
});

test('email can no longer be used to log in', function () {
    $user = User::factory()->create(['email' => 'carlos@siut.org']);

    Livewire::test(Login::class)
        ->fillForm(['curp' => 'carlos@siut.org', 'password' => 'admin123'])
        ->call('authenticate')
        ->assertHasFormErrors(['curp']);

    $this->assertGuest();
});

test('wrong password is rejected', function () {
    User::factory()->create(['curp' => 'MELC950515HDFRRN09']);

    Livewire::test(Login::class)
        ->fillForm(['curp' => 'MELC950515HDFRRN09', 'password' => 'wrong-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['curp']);

    $this->assertGuest();
});

test('inactive user cannot log in', function () {
    User::factory()->inactive()->create(['curp' => 'MELC950515HDFRRN09']);

    Livewire::test(Login::class)
        ->fillForm(['curp' => 'MELC950515HDFRRN09', 'password' => 'admin123'])
        ->call('authenticate')
        ->assertHasFormErrors(['curp']);

    $this->assertGuest();
});

test('curp is required to register', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/d.jpg', 'content');
    $t = $staging->stage('ine', 'temp/d.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza Lopez',
        'email' => 'carlos.mendoza@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => '',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $t,
        'nomina_token' => $t,
        'photo_token' => $t,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['curp']);
});

test('curp must have 18 characters to register', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/d.jpg', 'content');
    $t = $staging->stage('ine', 'temp/d.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza Lopez',
        'email' => 'carlos.mendoza@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'CORTA',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $t,
        'nomina_token' => $t,
        'photo_token' => $t,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['curp']);
});

test('registration stores curp in uppercase and the new user can log in', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/d.jpg', 'content');
    $t1 = $staging->stage('ine', 'temp/d.jpg');
    $t2 = $staging->stage('nomina', 'temp/d.jpg');
    $t3 = $staging->stage('photo', 'temp/d.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza Lopez',
        'email' => 'carlos.mendoza@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'melc950515hdfrrn09',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $t1,
        'nomina_token' => $t2,
        'photo_token' => $t3,
    ])->assertOk();

    expect(User::where('email', 'carlos.mendoza@siut.org')->value('curp'))->toBe('MELC950515HDFRRN09');

    auth()->logout();

    Livewire::test(Login::class)
        ->fillForm(['curp' => 'melc950515hdfrrn09', 'password' => 'SecurePassword123!'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticated();
});

test('registration rejects a duplicated curp regardless of casing', function () {
    User::factory()->create(['curp' => 'MELC950515HDFRRN09']);

    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/d.jpg', 'content');
    $t = $staging->stage('ine', 'temp/d.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza Lopez',
        'email' => 'carlos.mendoza@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'melc950515hdfrrn09',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $t,
        'nomina_token' => $t,
        'photo_token' => $t,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['curp']);

    expect(User::where('email', 'carlos.mendoza@siut.org')->exists())->toBeFalse();
});

test('model normalizes curp on assignment', function () {
    $user = User::factory()->create(['curp' => '  melc950515hdfrrn09 ']);

    expect($user->fresh()->curp)->toBe('MELC950515HDFRRN09');
});

test('admin user form requires curp', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Nuevo',
            'surnames' => 'Usuario',
            'email' => 'nuevo@siut.org',
            'role' => UserRole::Agremiado->value,
            'birth_date' => '1990-01-01',
            'password' => 'password-123',
            'curp' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['curp' => ['required']]);
});

test('admin can edit a user keeping its own curp', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['curp' => 'MELC950515HDFRRN09']);

    $this->actingAs($admin);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();
});
