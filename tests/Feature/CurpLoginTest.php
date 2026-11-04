<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('public');
});

function registrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Carlos',
        'surnames' => 'Mendoza Lopez',
        'email' => 'carlos.mendoza@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'MELC950515HDFRRN09',
        'photo_path' => UploadedFile::fake()->image('carlos.jpg'),
        'password' => 'SecurePassword123!',
        'passwordConfirmation' => 'SecurePassword123!',
    ], $overrides);
}

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
    Livewire::test(Register::class)
        ->fillForm(registrationData(['curp' => null]))
        ->call('register')
        ->assertHasFormErrors(['curp' => ['required']]);
});

test('curp must have 18 characters to register', function () {
    Livewire::test(Register::class)
        ->fillForm(registrationData(['curp' => 'CORTA']))
        ->call('register')
        ->assertHasFormErrors(['curp']);
});

test('registration stores curp in uppercase and the new user can log in', function () {
    Livewire::test(Register::class)
        ->fillForm(registrationData(['curp' => 'melc950515hdfrrn09']))
        ->call('register')
        ->assertHasNoFormErrors();

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

    Livewire::test(Register::class)
        ->fillForm(registrationData(['curp' => 'melc950515hdfrrn09']))
        ->call('register')
        ->assertHasFormErrors(['curp']);

    expect(User::where('email', 'carlos.mendoza@siut.org')->exists())->toBeFalse();
});

test('model normalizes curp on assignment', function () {
    $user = User::factory()->create(['curp' => '  melc950515hdfrrn09 ']);

    expect($user->fresh()->curp)->toBe('MELC950515HDFRRN09');
});

test('admin user form requires curp', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(\App\Filament\Resources\Users\Pages\CreateUser::class)
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

    Livewire::test(\App\Filament\Resources\Users\Pages\EditUser::class, ['record' => $user->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();
});
