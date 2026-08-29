<?php

use App\Enums\UserRole;
use App\Filament\Pages\Profile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('guest cannot access profile page', function () {
    $this->get('/portal/profile')
        ->assertRedirect('/portal/login');
});

test('authenticated user can view profile page with self details', function () {
    $user = User::factory()->create([
        'name' => 'Alejandro',
        'surnames' => 'Gomez Perez',
        'email' => 'alejandro.gomez@siut.org',
        'role' => UserRole::Agremiado,
        'curp' => 'GOPA900101HDFRRN01',
        'category' => 'Docente Titular',
        'nss' => '12345678901',
        'salary' => 18500.50,
        'phone' => '9931234567',
        'address' => 'Av. Universidad #123, Col. Magisterial',
    ]);

    $this->actingAs($user);

    $this->get('/portal/profile')
        ->assertSuccessful()
        ->assertSee('Mi Perfil');

    Livewire::test(Profile::class)
        ->assertFormSet([
            'name' => 'Alejandro',
            'surnames' => 'Gomez Perez',
            'email' => 'alejandro.gomez@siut.org',
            'phone' => '9931234567',
            'address' => 'Av. Universidad #123, Col. Magisterial',
            'salary' => '18500.50',
        ])
        ->assertSee('GOPA900101HDFRRN01')
        ->assertSee('Docente Titular')
        ->assertSee('12345678901')
        ->assertSee('Agremiado');
});

test('authenticated user can update their personal information and salary', function () {
    $user = User::factory()->create([
        'name' => 'Beatriz',
        'surnames' => 'Hernández',
        'email' => 'beatriz@siut.org',
        'phone' => '9931112233',
        'salary' => 15000.00,
        'address' => 'Calle Antigua 10',
    ]);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->fillForm([
            'name' => 'Beatriz Elena',
            'surnames' => 'Hernández Ruiz',
            'phone' => '9939998877',
            'salary' => 21000.00,
            'address' => 'Calle Nueva 45',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Beatriz Elena')
        ->and($user->surnames)->toBe('Hernández Ruiz')
        ->and($user->phone)->toBe('9939998877')
        ->and((float) $user->salary)->toBe(21000.00)
        ->and($user->address)->toBe('Calle Nueva 45');
});

test('profile photo can be uploaded and saved on public disk', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'phone' => '1234567890',
    ]);

    $this->actingAs($user);

    $file = UploadedFile::fake()->image('avatar.jpg');

    Livewire::test(Profile::class)
        ->fillForm([
            'photo_path' => $file,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->photo_path);
});

test('profile photo provides root relative url on edit to avoid cors issues', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/my-photo.jpg', 'image-content');

    $user = User::factory()->create([
        'phone' => '1234567890',
        'photo_path' => 'profile-photos/my-photo.jpg',
    ]);

    $this->actingAs($user);

    $test = Livewire::test(Profile::class);
    $component = $test->instance()->getSchema('form')->getComponent('photo_path');
    $uploadedFiles = $component->getUploadedFiles();

    expect($uploadedFiles)->not->toBeEmpty();
    $fileData = array_values($uploadedFiles)[0];
    expect($fileData['url'])->toBe('/storage/profile-photos/my-photo.jpg');
});

test('user can update password with current password verification', function () {
    $user = User::factory()->create([
        'phone' => '1234567890',
        'password' => 'old-strong-password-123',
    ]);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->fillForm([
            'currentPassword' => 'old-strong-password-123',
            'password' => 'new-secret-password-456',
            'passwordConfirmation' => 'new-secret-password-456',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect(Hash::check('new-secret-password-456', $user->password))->toBeTrue();
});

test('user cannot change password with incorrect current password', function () {
    $user = User::factory()->create([
        'phone' => '1234567890',
        'password' => 'actual-password-123',
    ]);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->fillForm([
            'currentPassword' => 'wrong-password',
            'password' => 'new-secret-password-456',
            'passwordConfirmation' => 'new-secret-password-456',
        ])
        ->call('save')
        ->assertHasFormErrors(['currentPassword']);

    $user->refresh();

    expect(Hash::check('actual-password-123', $user->password))->toBeTrue();
});

test('required fields are validated on profile update', function () {
    $user = User::factory()->create([
        'phone' => '1234567890',
    ]);

    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->fillForm([
            'name' => '',
            'surnames' => '',
            'email' => '',
        ])
        ->call('save')
        ->assertHasFormErrors([
            'name' => 'required',
            'surnames' => 'required',
            'email' => 'required',
        ]);
});

test('sidebar navigation contains Mi Perfil link', function () {
    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    $this->actingAs($user);

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('Mi Perfil');
});
