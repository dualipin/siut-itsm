<?php

use App\Enums\UserRole;
use App\Filament\Pages\Profile;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('agremiado cannot edit email birth_date or salary from the profile page', function () {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
        'email' => 'original@siut.org',
        'birth_date' => '1990-01-01',
        'salary' => 15000.00,
        'is_active' => true,
    ]);

    $this->actingAs($agremiado);

    Livewire::test(Profile::class)
        ->assertFormFieldIsDisabled('email')
        ->assertFormFieldIsDisabled('birth_date')
        ->assertFormFieldIsDisabled('salary')
        ->fillForm([
            'currentPassword' => 'admin123',
            'email' => 'tampered@siut.org',
            'birth_date' => '1995-05-05',
            'salary' => 99999.00,
            'surnames' => 'Updated Surnames',
        ])
        ->call('save')
        ->assertHasNoErrors();

    $agremiado->refresh();

    // Surnames was editable and updated
    expect($agremiado->surnames)->toBe('Updated Surnames');

    // Email, birth_date, and salary remained unchanged
    expect($agremiado->email)->toBe('original@siut.org');
    expect($agremiado->birth_date->format('Y-m-d'))->toBe('1990-01-01');
    expect((float) $agremiado->salary)->toBe(15000.00);
});

test('admin can edit profile fields without restrictions', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'admin@siut.org',
        'birth_date' => '1985-06-15',
        'salary' => 25000.00,
        'is_active' => true,
    ]);

    $this->actingAs($admin);

    Livewire::test(Profile::class)
        ->assertFormFieldIsEnabled('email')
        ->assertFormFieldIsEnabled('birth_date')
        ->assertFormFieldIsEnabled('salary')
        ->fillForm([
            'currentPassword' => 'admin123',
            'email' => 'admin_updated@siut.org',
            'birth_date' => '1988-10-20',
            'salary' => 32000.00,
        ])
        ->call('save')
        ->assertHasNoErrors();

    $admin->refresh();
    expect($admin->email)->toBe('admin_updated@siut.org');
    expect($admin->birth_date->format('Y-m-d'))->toBe('1988-10-20');
    expect((float) $admin->salary)->toBe(32000.00);
});
