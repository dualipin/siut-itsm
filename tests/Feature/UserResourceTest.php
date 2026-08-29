<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($admin);
});

test('password is required when creating a user', function () {
    Livewire::test(CreateUser::class)
        ->call('create')
        ->assertHasErrors(['data.password' => ['required']]);
});

test('password is not required when editing an existing user', function () {
    $user = User::factory()->create([
        'phone' => '1234567890',
        'password' => 'initial-secret-123',
    ]);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->call('save')
        ->assertHasNoErrors(['data.password']);

    $user->refresh();
    expect(Hash::check('initial-secret-123', $user->password))->toBeTrue();
});

test('password can be updated when editing an existing user', function () {
    $user = User::factory()->create([
        'phone' => '1234567890',
        'password' => 'initial-secret-123',
    ]);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->set('data.password', 'new-valid-password')
        ->call('save')
        ->assertHasNoErrors(['data.password']);

    $user->refresh();
    expect(Hash::check('new-valid-password', $user->password))->toBeTrue();
});

test('profile photo can be uploaded to public disk on edit', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'phone' => '1234567890',
    ]);

    $file = UploadedFile::fake()->image('profile.jpg');

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->set('data.photo_path', $file)
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->photo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($user->photo_path);
});

test('users list page can render table with photo column', function () {
    $user = User::factory()->create([
        'phone' => '1234567890',
        'photo_path' => 'profile-photos/sample.jpg',
    ]);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$user])
        ->assertTableColumnExists('photo_path');
});

test('profile photo provides root relative url on edit to avoid cors infinite loading', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile-photos/sample.jpg', 'fake-image-content');

    $user = User::factory()->create([
        'phone' => '1234567890',
        'photo_path' => 'profile-photos/sample.jpg',
    ]);

    $test = Livewire::test(EditUser::class, ['record' => $user->getRouteKey()]);
    $component = $test->instance()->getSchema('form')->getComponent('photo_path');
    $uploadedFiles = $component->getUploadedFiles();

    expect($uploadedFiles)->not->toBeEmpty();
    $fileData = array_values($uploadedFiles)[0];
    expect($fileData['url'])->toBe('/storage/profile-photos/sample.jpg');
});

test('non-admin user cannot access users resource', function () {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    $this->actingAs($agremiado);

    $this->get('/portal/users')->assertForbidden();
});
