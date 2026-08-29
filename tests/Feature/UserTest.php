<?php

use App\Enums\UserRole;
use App\Models\User;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

test('user role enum has expected cases and values', function () {
    expect(UserRole::Agremiado->value)->toBe('agremiado');
    expect(UserRole::Lider->value)->toBe('lider');
    expect(UserRole::Admin->value)->toBe('admin');
    expect(UserRole::cases())->toHaveCount(3);

    expect(UserRole::Agremiado->getLabel())->toBe('Agremiado');
    expect(UserRole::Lider->getLabel())->toBe('Líder');
    expect(UserRole::Admin->getLabel())->toBe('Administrador');
});

test('user model casts attributes correctly', function () {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
        'birth_date' => '1990-05-15',
        'hiring_date' => '2020-01-10',
        'salary' => 25000.50,
    ]);

    expect($user->role)->toBe(UserRole::Admin);
    expect($user->is_active)->toBeTrue();
    expect($user->birth_date)->toBeInstanceOf(Carbon::class);
    expect($user->birth_date->format('Y-m-d'))->toBe('1990-05-15');
    expect($user->hiring_date)->toBeInstanceOf(Carbon::class);
    expect($user->hiring_date->format('Y-m-d'))->toBe('2020-01-10');
    expect($user->salary)->toBe('25000.50');
});

test('user role helper methods work accurately', function () {
    $admin = User::factory()->admin()->make();
    $lider = User::factory()->lider()->make();
    $agremiado = User::factory()->agremiado()->make();

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isLider())->toBeFalse();
    expect($admin->isAgremiado())->toBeFalse();

    expect($lider->isAdmin())->toBeFalse();
    expect($lider->isLider())->toBeTrue();
    expect($lider->isAgremiado())->toBeFalse();

    expect($agremiado->isAdmin())->toBeFalse();
    expect($agremiado->isLider())->toBeFalse();
    expect($agremiado->isAgremiado())->toBeTrue();
});

test('user full name attribute combines name and surnames', function () {
    $user = User::factory()->make([
        'name' => 'Juan',
        'surnames' => 'Pérez López',
    ]);

    expect($user->full_name)->toBe('Juan Pérez López');
});

test('user supports soft deletes', function () {
    $user = User::factory()->create();

    $user->delete();

    expect($user->trashed())->toBeTrue();
    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});

test('user factory states configure attributes as expected', function () {
    $active = User::factory()->active()->make();
    $inactive = User::factory()->inactive()->make();
    $unverified = User::factory()->unverified()->make();
    $withPhoto = User::factory()->withPhoto('custom/avatar.jpg')->make();
    $trashed = User::factory()->trashed()->create();

    expect($active->is_active)->toBeTrue();
    expect($inactive->is_active)->toBeFalse();
    expect($unverified->email_verified_at)->toBeNull();
    expect($withPhoto->photo_path)->toBe('custom/avatar.jpg');
    expect($trashed->trashed())->toBeTrue();
});

test('user implements HasAvatar and resolves filament avatar url correctly', function () {
    $userWithoutPhoto = User::factory()->make([
        'photo_path' => null,
    ]);
    expect($userWithoutPhoto)->toBeInstanceOf(HasAvatar::class);
    expect($userWithoutPhoto->getFilamentAvatarUrl())->toBeNull();

    $userWithPhoto = User::factory()->make([
        'photo_path' => 'profile-photos/avatar.jpg',
    ]);
    expect($userWithPhoto->getFilamentAvatarUrl())->toBe(Storage::disk('public')->url('profile-photos/avatar.jpg'));

    $userWithUrl = User::factory()->make([
        'photo_path' => 'https://example.com/avatar.jpg',
    ]);
    expect($userWithUrl->getFilamentAvatarUrl())->toBe('https://example.com/avatar.jpg');
});
