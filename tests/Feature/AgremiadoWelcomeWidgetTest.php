<?php

use App\Enums\LoanOperationalState;
use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Messages;
use App\Filament\Pages\Profile;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\UserRequests\UserRequestResource;
use App\Filament\Widgets\AgremiadoWelcomeWidget;
use App\Filament\Widgets\DirectivaAccountWidget;
use App\Models\Loan;
use App\Models\LoanOperationalSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('guests cannot view role-restricted dashboard widgets', function (): void {
    expect(AgremiadoWelcomeWidget::canView())->toBeFalse();
    expect(DirectivaAccountWidget::canView())->toBeFalse();
});

test('agremiado sees welcome header and four quick links without account or qr widgets', function (): void {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
        'name' => 'Sofía',
        'is_active' => true,
    ]);

    $response = $this->actingAs($agremiado)->get('/portal');

    $response->assertSuccessful();
    $response->assertSee($agremiado->name);
    $response->assertSee('Solicitar préstamo');
    $response->assertSee(LoanResource::getUrl('apply'));
    $response->assertSee('Nueva solicitud');
    $response->assertSee(UserRequestResource::getUrl('create'));
    $response->assertSee('Mensajería');
    $response->assertSee(Messages::getUrl());
    $response->assertSee('Mi Perfil');
    $response->assertSee(Profile::getUrl());
    $response->assertDontSee('fi-account-widget');
    $response->assertDontSee('data-vue="portal/qr-generator"', escape: false);

    expect(AgremiadoWelcomeWidget::canView())->toBeTrue();
    expect(DirectivaAccountWidget::canView())->toBeFalse();

    Livewire::test(AgremiadoWelcomeWidget::class)
        ->assertSuccessful()
        ->assertSee($agremiado->name);
});

test('agremiado with draft loan sees continuar borrador linking directly to the draft view', function (): void {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
        'is_active' => true,
    ]);

    $draftLoan = Loan::factory()->create([
        'user_id' => $agremiado->id,
        'status' => LoanStatus::Borrador,
    ]);

    $response = $this->actingAs($agremiado)->get('/portal');

    $response->assertSuccessful();
    $response->assertSee('Continuar borrador');
    $response->assertSee(LoanResource::getUrl('view', ['record' => $draftLoan]));
    $response->assertDontSee(LoanResource::getUrl('apply'));
});

test('agremiado sees unavailability state instead of apply link when loans service is temporarily out of service', function (): void {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
        'is_active' => true,
    ]);

    LoanOperationalSetting::query()->create([
        'state' => LoanOperationalState::TemporalmenteFueraDeServicio,
        'target_fiscal_year' => now()->year,
        'suspension_message' => 'Mantenimiento programado de préstamos.',
    ]);

    $response = $this->actingAs($agremiado)->get('/portal');

    $response->assertSuccessful();
    $response->assertSee('Temporalmente fuera de servicio');
    $response->assertDontSee('Solicitar préstamo');
    $response->assertDontSee(LoanResource::getUrl('apply'));
});

test('directiva roles keep account and qr widgets and do not see agremiado welcome header', function (UserRole $role): void {
    $user = User::factory()->create([
        'role' => $role,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get('/portal');

    $response->assertSuccessful();
    $response->assertSee('fi-account-widget');
    $response->assertSee('data-vue="portal/qr-generator"', escape: false);
    $response->assertDontSee('Solicitar préstamo');
    $response->assertDontSee('Continuar borrador');

    expect(DirectivaAccountWidget::canView())->toBeTrue();
    expect(AgremiadoWelcomeWidget::canView())->toBeFalse();
})->with([
    'admin' => UserRole::Admin,
    'lider' => UserRole::Lider,
]);
