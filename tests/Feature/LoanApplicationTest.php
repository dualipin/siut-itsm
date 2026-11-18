<?php

use App\Enums\LoanAiStatus;
use App\Enums\LoanGeneratedDocumentType;
use App\Enums\LoanOperationalState;
use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Loans\Pages\ApplyLoan;
use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Models\Benefit;
use App\Models\Loan;
use App\Models\LoanAmortizationRow;
use App\Models\LoanEvidence;
use App\Models\LoanGeneratedDocument;
use App\Models\User;
use App\Services\LoanOperationalStateService;
use App\Services\LoanService;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Wizard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-06-01');
    Storage::fake('local');
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('redirects unauthenticated guests to login', function () {
    $response = $this->get(ApplyLoan::getUrl());
    $response->assertRedirect('/portal/login');
});

it('allows authenticated users to access the application form', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    $response = $this->actingAs($user)->get(ApplyLoan::getUrl());

    $response->assertOk();
    $response->assertSee('Nueva Solicitud de Préstamo');
    $response->assertDontSee('Tasa de Interés Mensual');
    $response->assertDontSee('Primera Quincena');
});

it('prefills the latest bank details for a returning applicant', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    Loan::factory()->for($user)->create([
        'bank_name' => 'Santander',
        'bank_account' => '9876543210',
        'interbank_clabe' => '014180009876543210',
    ]);

    $this->actingAs($user);

    Livewire::test(ApplyLoan::class)
        ->assertFormSet([
            'bank_name' => 'Santander',
            'bank_account' => '9876543210',
            'interbank_clabe' => '014180009876543210',
        ]);
});

it('advances from the simulation step after synchronizing benefit evidence', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $benefit = Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'is_active' => true,
        'collection_month' => 12,
        'collection_day' => 15,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'mixed',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
        ])
        ->set('data.benefits', [
            'item-1' => ['benefit_id' => (string) $benefit->id, 'amount' => 5000],
        ]);

    $initialEvidences = $component->get('data.benefit_evidences');
    expect($initialEvidences)->toHaveCount(1);
    $initialKey = array_key_first($initialEvidences);
    expect($initialEvidences[$initialKey]['benefit_id'])->toBe($benefit->id)
        ->and($initialEvidences[$initialKey]['benefit_name'])->toBe('Aguinaldo')
        ->and($initialEvidences[$initialKey]['ai_status'])->toBe('pendiente');

    $wizard = $component->instance()->form->getComponent(
        fn ($component): bool => $component instanceof Wizard,
    );

    $component
        ->call('callSchemaComponentMethod', $wizard->getKey(), 'nextStep', [0]);

    $component->assertHasNoFormErrors();

    $syncedEvidences = $component->get('data.benefit_evidences');
    expect($syncedEvidences)->toHaveCount(1);
    $syncedKey = array_key_first($syncedEvidences);
    expect($syncedKey)->toBe($initialKey)
        ->and($syncedEvidences[$syncedKey]['benefit_id'])->toBe($benefit->id)
        ->and($syncedEvidences[$syncedKey]['benefit_name'])->toBe('Aguinaldo');

    $repeater = $component->instance()->form->getComponent(
        fn ($c): bool => $c instanceof Repeater && $c->getName() === 'benefit_evidences',
    );
    expect($repeater->getItemLabel($initialKey))->toBe('Comprobante de Prestación: Aguinaldo');

    $currentWizard = $component->instance()->form->getComponent(
        fn ($component): bool => $component instanceof Wizard,
    );

    expect($currentWizard->getCurrentStepIndex())->toBe(1);
});

it('allows an authenticated agremiado to submit mixed loan wizard with automatically populated benefit evidence', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $benefit = Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'is_active' => true,
        'collection_month' => 12,
        'collection_day' => 15,
    ]);
    $nominaFile = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');
    $benefitFile = UploadedFile::fake()->create('aguinaldo.pdf', 100, 'application/pdf');

    Config::set('services.document_ai.enabled', false);

    $this->actingAs($user);

    $dummySignatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'mixed',
            'nomina_amount' => 10000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'interbank_clabe' => '012180001234567890',
            'nomina_evidence' => $nominaFile,
        ])
        ->set('data.benefits', [
            'item-1' => ['benefit_id' => (string) $benefit->id, 'amount' => 5000],
        ]);

    $benefitEvidences = $component->get('data.benefit_evidences');
    expect($benefitEvidences)->toHaveCount(1);
    $evidenceKey = array_key_first($benefitEvidences);
    expect($benefitEvidences[$evidenceKey]['benefit_id'])->toBe($benefit->id)
        ->and($benefitEvidences[$evidenceKey]['benefit_name'])->toBe('Aguinaldo');

    $component->set("data.benefit_evidences.{$evidenceKey}.file", [$benefitFile])
        ->set('data.signature_data', $dummySignatureData)
        ->set('data.accept_terms', true)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('loans', [
        'user_id' => $user->id,
        'status' => LoanStatus::Enviada->value,
        'requested_amount' => 15000,
        'payment_type' => 'mixed',
    ]);

    $this->assertDatabaseHas('loan_evidences', [
        'source_type' => 'nomina',
    ]);

    $this->assertDatabaseHas('loan_evidences', [
        'source_type' => 'benefit',
        'benefit_id' => $benefit->id,
    ]);
});

it('updates benefit evidence rows dynamically when benefits change or payment type switches', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $benefit1 = Benefit::factory()->create(['name' => 'Aguinaldo', 'is_active' => true]);
    $benefit2 = Benefit::factory()->create(['name' => 'Prima Vacacional', 'is_active' => true]);

    $this->actingAs($user);

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'mixed',
            'nomina_amount' => 5000,
            'nomina_fortnights' => 2,
        ])
        ->set('data.benefits', [
            'item-1' => ['benefit_id' => (string) $benefit1->id, 'amount' => 3000],
        ]);

    $evidences = $component->get('data.benefit_evidences');
    expect($evidences)->toHaveCount(1);
    $itemKey1 = array_key_first($evidences);
    expect($evidences[$itemKey1]['benefit_id'])->toBe($benefit1->id);

    // Add second benefit
    $component->set('data.benefits', [
        'item-1' => ['benefit_id' => (string) $benefit1->id, 'amount' => 3000],
        'item-2' => ['benefit_id' => (string) $benefit2->id, 'amount' => 2000],
    ]);

    $evidencesAfterAdd = $component->get('data.benefit_evidences');
    expect($evidencesAfterAdd)->toHaveCount(2)
        ->and($evidencesAfterAdd[$itemKey1]['benefit_id'])->toBe($benefit1->id);

    // Switch payment type to nomina
    $component->fillForm([
        'payment_type' => 'nomina',
    ]);

    expect($component->get('data.benefit_evidences'))->toBeEmpty();
});

it('uses the most recent bank details without changing previous applications', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $previousLoan = Loan::factory()->for($user)->create([
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
        'interbank_clabe' => '012180001234567890',
    ]);
    $latestLoan = Loan::factory()->for($user)->create([
        'bank_name' => 'Santander',
        'bank_account' => '9876543210',
        'interbank_clabe' => '014180009876543210',
    ]);

    expect(app(LoanService::class)->latestBankDetails($user))->toBe([
        'bank_name' => $latestLoan->bank_name,
        'bank_account' => $latestLoan->bank_account,
        'interbank_clabe' => $latestLoan->interbank_clabe,
    ]);

    expect($previousLoan->fresh()->bank_name)->toBe('BBVA');
});

it('allows an authenticated agremiado to submit the Filament loan wizard', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');

    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'clasificacion' => 'recibo_nomina',
                            'campos' => [
                                'nombre_trabajador' => $user->full_name,
                                'confianza' => 0.95,
                            ],
                            'confianza' => 0.95,
                            'observaciones' => 'Recibo de nómina válido.',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($user);

    $dummySignatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'interbank_clabe' => '012180001234567890',
            'nomina_evidence' => $file,
            'signature_data' => $dummySignatureData,
            'accept_terms' => true,
        ])
        ->assertFormSet(['nomina_ai_status' => LoanAiStatus::Valido->value])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('loans', [
        'user_id' => $user->id,
        'status' => LoanStatus::Enviada->value,
        'requested_amount' => 15000,
        'monthly_rate' => null,
        'first_fortnight_date' => '2026-06-30 00:00:00',
    ]);

    $this->assertDatabaseHas('loan_evidences', [
        'source_type' => 'nomina',
        'ai_status' => LoanAiStatus::Valido->value,
        'ai_classification' => 'recibo_nomina',
    ]);
});

it('blocks submission when the evidence is invalid and explains how to replace it', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->create('documento-ajeno.pdf', 100, 'application/pdf');

    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'clasificacion' => 'invalido',
                            'campos' => [],
                            'confianza' => 0.99,
                            'observaciones' => 'El documento no corresponde.',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($user);

    $dummySignatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_data' => $dummySignatureData,
            'accept_terms' => true,
        ])
        ->call('create')
        ->assertNotified();

    expect(Loan::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('allows submission for manual review when automatic document review is unavailable', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');

    Config::set('services.document_ai.enabled', false);

    $this->actingAs($user);

    $dummySignatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_data' => $dummySignatureData,
            'accept_terms' => true,
        ])
        ->assertFormSet(['nomina_ai_status' => LoanAiStatus::FalloTecnico->value])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('loans', [
        'user_id' => $user->id,
        'status' => LoanStatus::Enviada->value,
    ]);

    $this->assertDatabaseHas('loan_evidences', [
        'ai_status' => LoanAiStatus::FalloTecnico->value,
        'manual_review_status' => 'pending',
    ]);
});

it('stores a loan draft and validates financial inputs', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    $response = $this->actingAs($user)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 15000,
        'nomina_amount' => 15000,
        'nomina_fortnights' => 6,
        'first_fortnight_date' => '2026-07-15',
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
        'interbank_clabe' => '012180001234567890',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('loan.status', LoanStatus::Borrador->value);
    $response->assertJsonPath('loan.folio', fn ($folio) => str_starts_with($folio, 'SOL-2026-'));

    $this->assertDatabaseHas('loans', ['user_id' => $user->id,
        'status' => LoanStatus::Borrador->value,
        'requested_amount' => 15000,
        'first_fortnight_date' => '2026-06-30 00:00:00',
    ]);
});

it('requires a bank and at least one account identifier', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    $response = $this->actingAs($user)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 15000,
        'nomina_amount' => 15000,
        'nomina_fortnights' => 6,
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['bank_name', 'bank_account', 'interbank_clabe']);
});

it('accepts either account or clabe and validates clabe as up to 18 digits', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $payload = [
        'payment_type' => 'nomina',
        'requested_amount' => 15000,
        'nomina_amount' => 15000,
        'nomina_fortnights' => 6,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ];

    $this->actingAs($user)
        ->postJson('/api/prestamos', $payload)
        ->assertOk();

    $response = $this->actingAs(User::factory()->create(['role' => UserRole::Agremiado]))
        ->postJson('/api/prestamos', [
            ...$payload,
            'bank_account' => null,
            'interbank_clabe' => '1234567890123456789',
        ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['interbank_clabe']);
});

it('allows applicant to store a draft without monthly_rate leaving it for admin decision', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    // Omit monthly_rate entirely
    $response = $this->actingAs($user)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 18000,
        'nomina_amount' => 18000,
        'nomina_fortnights' => 6,
        'first_fortnight_date' => '2026-07-15',
        'bank_name' => 'Santander',
        'bank_account' => '9876543210',
        'interbank_clabe' => '014180009876543210',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('loan.monthly_rate', null);

    $this->assertDatabaseHas('loans', [
        'user_id' => $user->id,
        'requested_amount' => 18000,
        'monthly_rate' => null,
    ]);
});

it('allows applying in late november with payment fortnights in december up to december 31', function () {
    Carbon::setTestNow('2026-11-15');

    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    $response = $this->actingAs($user)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 5000,
        'nomina_amount' => 5000,
        'nomina_fortnights' => 2,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('loan.first_fortnight_date', fn ($date) => str_starts_with($date, '2026-12-15'));
    $response->assertJsonPath('loan.nomina_fortnights', 2);

    $this->assertDatabaseHas('loans', [
        'user_id' => $user->id,
        'status' => LoanStatus::Borrador->value,
        'first_fortnight_date' => '2026-12-15 00:00:00',
        'nomina_fortnights' => 2,
    ]);
});

it('allows draft creation, portal application, and submission in december while Activo when eligible dates exist', function () {
    Carbon::setTestNow('2026-12-05');
    Config::set('services.document_ai.enabled', false);
    Config::set('livewire.temporary_file_upload.cleanup', false);

    $apiUser = User::factory()->create(['role' => UserRole::Agremiado]);

    // API draft creation + submission in December (Dec 31 fortnight is eligible)
    $draftResponse = $this->actingAs($apiUser)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 1,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ]);

    $draftResponse->assertOk()
        ->assertJsonPath('loan.status', LoanStatus::Borrador->value)
        ->assertJsonPath('loan.folio', fn (string $folio): bool => str_starts_with($folio, 'SOL-2026-'));

    $loanId = (int) $draftResponse->json('loan.id');
    $loan = Loan::findOrFail($loanId);
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    $this->actingAs($apiUser)->postJson("/api/prestamos/{$loanId}/firmar", [
        'signature_data' => $dummySig,
    ])->assertOk();

    LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo-dec.pdf',
        'file_hash' => hash('sha256', 'dummy-dec'),
        'file_name' => 'recibo-dec.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Valido,
        'ai_classification' => 'recibo_nomina',
        'ai_confidence' => 0.95,
        'manual_review_status' => 'pending',
    ]);

    $this->actingAs($apiUser)
        ->postJson("/api/prestamos/{$loanId}/enviar")
        ->assertOk();

    expect($loan->fresh()->status)->toBe(LoanStatus::Enviada);

    // Portal ListLoans & ApplyLoan on Dec 5
    $portalUser = User::factory()->create(['role' => UserRole::Agremiado]);
    $this->actingAs($portalUser);

    Livewire::test(ListLoans::class)
        ->assertActionEnabled('createLoan')
        ->assertDontSee('Sin fechas disponibles en el ejercicio actual');

    $file = UploadedFile::fake()->createWithContent('recibo.pdf', "%PDF-1.4\n%fake\n");

    Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 8000,
            'nomina_fortnights' => 1,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_data' => $dummySig,
            'accept_terms' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('loans', [
        'user_id' => $portalUser->id,
        'status' => LoanStatus::Enviada->value,
        'first_fortnight_date' => '2026-12-31 00:00:00',
    ]);
});

it('allows benefit loan application on december 15 while Activo when a future active benefit exists', function () {
    Carbon::setTestNow('2026-12-15');
    Config::set('services.document_ai.enabled', false);
    Config::set('livewire.temporary_file_upload.cleanup', false);

    $benefit = Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $benefitFile = UploadedFile::fake()->createWithContent('aguinaldo.pdf', "%PDF-1.4\n%fake\n");
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    $this->actingAs($user);

    Livewire::test(ListLoans::class)
        ->assertActionEnabled('createLoan');

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'benefit',
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
        ])
        ->set('data.benefits', [
            'item-1' => ['benefit_id' => (string) $benefit->id, 'amount' => 5000],
        ]);

    $evidences = $component->get('data.benefit_evidences');
    $evidenceKey = array_key_first($evidences);

    $component->set("data.benefit_evidences.{$evidenceKey}.file", [$benefitFile])
        ->set('data.signature_data', $dummySig)
        ->set('data.accept_terms', true)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('loans', [
        'user_id' => $user->id,
        'status' => LoanStatus::Enviada->value,
        'payment_type' => 'benefit',
        'requested_amount' => 5000,
    ]);
});

it('blocks draft creation, submission, and portal application in december while Activo when no dates remain and informs about Cierre de anio', function () {
    // Create a signed draft with evidence earlier in the year
    Carbon::setTestNow('2026-11-15');
    $draftOwner = User::factory()->create(['role' => UserRole::Agremiado]);
    $loanService = app(LoanService::class);
    $draftLoan = $loanService->createDraft($draftOwner, [
        'payment_type' => 'nomina',
        'requested_amount' => 6000,
        'nomina_amount' => 6000,
        'nomina_fortnights' => 1,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ]);
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $loanService->signLoan($draftLoan, $dummySig);
    LoanEvidence::create([
        'loan_id' => $draftLoan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo-nov.pdf',
        'file_hash' => hash('sha256', 'dummy-nov'),
        'file_name' => 'recibo-nov.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Valido,
        'ai_classification' => 'recibo_nomina',
        'ai_confidence' => 0.95,
        'manual_review_status' => 'pending',
    ]);

    // Move to Dec 15 with no future benefits
    Carbon::setTestNow('2026-12-15');

    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    $createResponse = $this->actingAs($user)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 1,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ]);

    $createResponse->assertStatus(422)
        ->assertJsonValidationErrors(['loan']);
    expect($createResponse->json('errors.loan.0'))
        ->toContain('No hay fechas disponibles')
        ->toContain('Cierre de año')
        ->not->toContain('30 de noviembre');

    $submitResponse = $this->actingAs($draftOwner)
        ->postJson("/api/prestamos/{$draftLoan->id}/enviar");

    $submitResponse->assertStatus(422)
        ->assertJsonValidationErrors(['loan']);
    expect($submitResponse->json('errors.loan.0'))
        ->toContain('No hay fechas disponibles')
        ->toContain('Cierre de año')
        ->not->toContain('30 de noviembre');

    $this->actingAs($user);

    Livewire::test(ListLoans::class)
        ->assertActionDisabled('createLoan')
        ->assertSee('No hay fechas disponibles')
        ->assertSee('Cierre de año')
        ->assertDontSee('30 de noviembre');

    Livewire::test(ApplyLoan::class)
        ->assertNotified('Sin fechas disponibles en el ejercicio actual')
        ->assertRedirect(ListLoans::getUrl());
});

it('prevents a user from having more than one active loan without an authorized exception', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    // Already has an active loan in borrador
    Loan::factory()->for($user)->create([
        'status' => LoanStatus::Borrador,
    ]);

    $response = $this->actingAs($user)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 5000,
        'nomina_amount' => 5000,
        'nomina_fortnights' => 2,
        'first_fortnight_date' => '2026-07-15',
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['loan']);
});

it('invalidates drawn signature when loan terms change', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $loanService = app(LoanService::class);

    $loan = $loanService->createDraft($user, [
        'payment_type' => 'nomina',
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 4,
        'first_fortnight_date' => '2026-07-15',
    ]);

    // Sign the loan
    $dummySignatureData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $signedLoan = $loanService->signLoan($loan, $dummySignatureData);

    expect($signedLoan->isSignatureValid())->toBeTrue();

    // Now update terms (e.g. increase requested amount)
    $updatedLoan = $loanService->updateDraft($signedLoan, [
        'requested_amount' => 12000,
        'nomina_amount' => 12000,
    ]);

    expect($updatedLoan->isSignatureValid())->toBeFalse()
        ->and($updatedLoan->signature_data)->toBeNull();
});

it('submitting a loan requires a valid signature and complete evidence', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $loanService = app(LoanService::class);

    $loan = $loanService->createDraft($user, [
        'payment_type' => 'nomina',
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 4,
        'first_fortnight_date' => '2026-07-15',
    ]);

    // Attempting submit without signature -> 422
    $submitRes1 = $this->actingAs($user)->postJson("/api/prestamos/{$loan->id}/enviar");
    $submitRes1->assertStatus(422);

    // Sign it
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $this->actingAs($user)->postJson("/api/prestamos/{$loan->id}/firmar", [
        'signature_data' => $dummySig,
    ])->assertOk();

    // Attempting submit without evidence -> 422
    $submitRes2 = $this->actingAs($user)->postJson("/api/prestamos/{$loan->id}/enviar");
    $submitRes2->assertStatus(422);

    // Attach valid evidence
    LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Valido,
        'ai_classification' => 'recibo_nomina',
        'ai_confidence' => 0.95,
        'manual_review_status' => 'pending',
    ]);

    // Submit now succeeds
    $submitRes3 = $this->actingAs($user)->postJson("/api/prestamos/{$loan->id}/enviar");
    $submitRes3->assertOk();
    expect($loan->fresh()->status)->toBe(LoanStatus::Enviada)
        ->and($loan->fresh()->submitted_at)->not->toBeNull();
});

it('protects private document downloads with policy authorization', function () {
    $owner = User::factory()->create(['role' => UserRole::Agremiado]);
    $intruder = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->for($owner)->create([
        'status' => LoanStatus::Aprobada,
    ]);

    // Create a dummy generated document in storage
    Storage::disk('local')->put('loans/test/solicitud.pdf', '%PDF-1.4 dummy content');
    $document = LoanGeneratedDocument::create([
        'loan_id' => $loan->id,
        'document_type' => LoanGeneratedDocumentType::Solicitud,
        'version' => 1,
        'file_path' => 'loans/test/solicitud.pdf',
        'file_hash' => hash('sha256', '%PDF-1.4 dummy content'),
        'file_size' => strlen('%PDF-1.4 dummy content'),
        'generated_at' => now(),
    ]);

    // Intruder cannot download -> 403 Forbidden
    $this->actingAs($intruder)
        ->get("/portal/prestamos/{$loan->id}/documentos/{$document->id}")
        ->assertForbidden();

    // Owner can download -> 200 OK
    $this->actingAs($owner)
        ->get("/portal/prestamos/{$loan->id}/documentos/{$document->id}")
        ->assertOk();

    // Admin can download -> 200 OK
    $this->actingAs($admin)
        ->get("/portal/prestamos/{$loan->id}/documentos/{$document->id}")
        ->assertOk();
});

it('disallows selecting the same benefit more than once and disables it in sibling repeater items', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $benefit = Benefit::factory()->create([
        'is_active' => true,
        'collection_month' => 12,
        'collection_day' => 15,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'benefit',
        ])
        ->set('data.benefits', [
            'item-1' => ['benefit_id' => (string) $benefit->id, 'amount' => 5000],
            'item-2' => ['benefit_id' => null, 'amount' => 3000],
        ]);

    $repeater = $component->instance()->form->getComponent(
        fn ($component): bool => $component instanceof Repeater,
    );

    $containers = $repeater->getChildComponentContainers();
    $secondItemSelect = $containers['item-2']->getComponent(
        fn ($c): bool => $c instanceof Select,
    );

    expect($secondItemSelect->isOptionDisabled((string) $benefit->id, $benefit->name))->toBeTrue();

    $duplicateComponent = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'benefit',
        ])
        ->set('data.benefits', [
            'item-1' => ['benefit_id' => (string) $benefit->id, 'amount' => 5000],
            'item-2' => ['benefit_id' => (string) $benefit->id, 'amount' => 3000],
        ]);

    $wizard = $duplicateComponent->instance()->form->getComponent(
        fn ($component): bool => $component instanceof Wizard,
    );

    $duplicateComponent
        ->call('callSchemaComponentMethod', $wizard->getKey(), 'nextStep', [0])
        ->assertHasFormErrors(['benefits.item-2.benefit_id']);
});

it('advances from evidence step to final step when evidence is valid and in array form', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->image('recibo.png', 200, 200);

    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'clasificacion' => 'recibo_nomina',
                            'campos' => ['nombre_trabajador' => $user->full_name],
                            'confianza' => 0.95,
                            'observaciones' => 'Recibo válido.',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'interbank_clabe' => '012180001234567890',
            'nomina_evidence' => $file,
        ]);

    $getWizard = fn () => $component->instance()->form->getComponent(
        fn ($c): bool => $c instanceof Wizard,
    );

    // Step 0 (Simulación) -> Step 1 (Datos Bancarios)
    $component->call('callSchemaComponentMethod', $getWizard()->getKey(), 'nextStep', [0]);
    $component->assertHasNoFormErrors();
    expect($getWizard()->getCurrentStepIndex())->toBe(1);

    // Step 1 (Datos Bancarios) -> Step 2 (Evidencias)
    $component->call('callSchemaComponentMethod', $getWizard()->getKey(), 'nextStep', [1]);
    $component->assertHasNoFormErrors();
    expect($getWizard()->getCurrentStepIndex())->toBe(2);

    // Step 2 (Evidencias) -> Step 3 (Firma y Envío)
    $component->call('callSchemaComponentMethod', $getWizard()->getKey(), 'nextStep', [2]);
    $component->assertHasNoFormErrors();
    expect($getWizard()->getCurrentStepIndex())->toBe(3);
});

it('blocks advancing from evidence step to final step when evidence is invalid', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->image('recibo_ajeno.png', 200, 200);

    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');
    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'clasificacion' => 'invalido',
                            'campos' => [],
                            'confianza' => 0.99,
                            'observaciones' => 'No corresponde.',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->actingAs($user);

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'interbank_clabe' => '012180001234567890',
            'nomina_evidence' => $file,
        ]);

    $getWizard = fn () => $component->instance()->form->getComponent(
        fn ($c): bool => $c instanceof Wizard,
    );

    // Step 0 -> Step 1
    $component->call('callSchemaComponentMethod', $getWizard()->getKey(), 'nextStep', [0]);
    expect($getWizard()->getCurrentStepIndex())->toBe(1);

    // Step 1 -> Step 2
    $component->call('callSchemaComponentMethod', $getWizard()->getKey(), 'nextStep', [1]);
    expect($getWizard()->getCurrentStepIndex())->toBe(2);

    // Step 2 -> Step 3 should be blocked because evidence is invalid
    $component->call('callSchemaComponentMethod', $getWizard()->getKey(), 'nextStep', [2])
        ->assertNotified()
        ->assertNotDispatched('next-wizard-step');
});

it('requires drawn signature in final wizard step to submit loan', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');

    Config::set('services.document_ai.enabled', false);

    $this->actingAs($user);

    Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_type' => 'drawn',
            'signature_data' => null,
            'accept_terms' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['signature_data']);
});

it('blocks submission and requires drawn signature when efirma is selected', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');

    Config::set('services.document_ai.enabled', false);

    $this->actingAs($user);

    Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_type' => 'efirma',
            'accept_terms' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['signature_type']);
});

it('invalidates drawn signature and acceptance when loan simulation terms are changed', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    Config::set('services.document_ai.enabled', false);

    $this->actingAs($user);

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 10000,
            'nomina_fortnights' => 4,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_data' => $dummySig,
            'accept_terms' => true,
        ]);

    expect($component->get('data.signature_data'))->toBe($dummySig)
        ->and($component->get('data.accept_terms'))->toBeTrue();

    // Now user changes nomina amount in simulation step
    $component->fillForm([
        'nomina_amount' => 15000,
    ]);

    expect($component->get('data.signature_data'))->toBeNull()
        ->and($component->get('data.accept_terms'))->toBeFalse();
});

it('persists real drawn signature data on loan and generates pdfs with signature', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    Config::set('services.document_ai.enabled', false);

    $this->actingAs($user);

    Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_type' => 'drawn',
            'signature_data' => $dummySig,
            'accept_terms' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var Loan $loan */
    $loan = Loan::where('user_id', $user->id)->firstOrFail();

    expect($loan->signature_data)->toBe($dummySig)
        ->and($loan->signature_type)->toBe('drawn')
        ->and($loan->isSignatureValid())->toBeTrue()
        ->and($loan->status)->toBe(LoanStatus::Enviada);
});

it('allows an agremiado to view their submitted loan detail page without error', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->for($user)->submitted()->create();

    $this->actingAs($user)
        ->get("/portal/loans/{$loan->id}")
        ->assertOk()
        ->assertSee($loan->folio);

    Livewire::actingAs($user)
        ->test(ViewLoan::class, ['record' => $loan->getKey()])
        ->assertOk()
        ->assertSee($loan->folio);
});

it('correctly loads and displays amortization rows on the loan detail page', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->for($user)->approved()->create();

    LoanAmortizationRow::create([
        'loan_id' => $loan->id,
        'period' => 2,
        'type' => 'nomina',
        'label' => 'Quincena 1',
        'date' => '2026-07-15',
        'opening_balance' => 5000,
        'principal' => 2500,
        'interest' => 150,
        'payment' => 2650,
        'closing_balance' => 2500,
    ]);

    LoanAmortizationRow::create([
        'loan_id' => $loan->id,
        'period' => 1,
        'type' => 'extra_days',
        'label' => 'Días adicionales',
        'date' => '2026-06-30',
        'opening_balance' => 5000,
        'principal' => 0,
        'interest' => 50,
        'payment' => 50,
        'closing_balance' => 5000,
    ]);

    expect($loan->amortizationRows->pluck('period')->all())->toBe([1, 2]);

    $this->actingAs($user)
        ->get("/portal/loans/{$loan->id}")
        ->assertOk()
        ->assertSee('Días adicionales')
        ->assertSee('Quincena 1');
});

it('shows AI details on loan view only to administrators and leaders, hiding them from agremiados', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $lider = User::factory()->create(['role' => UserRole::Lider]);

    $loan = Loan::factory()->for($agremiado)->submitted()->create();

    LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'ai_status' => LoanAiStatus::Valido,
        'ai_classification' => 'recibo_nomina',
        'ai_confidence' => 0.95,
        'extracted_data' => ['nombre_trabajador' => 'Juan Pérez'],
        'manual_review_status' => 'pending',
    ]);

    // As agremiado: AI details must NOT be visible
    $this->actingAs($agremiado)
        ->get("/portal/loans/{$loan->id}")
        ->assertOk()
        ->assertSee('Comprobante de Nómina')
        ->assertSee('Aprobado')
        ->assertDontSee('Clasificación IA:')
        ->assertDontSee('Confianza:')
        ->assertDontSee('Nombre detectado:')
        ->assertDontSee('IA: Válido');

    Livewire::actingAs($agremiado)
        ->test(ViewLoan::class, ['record' => $loan->getKey()])
        ->assertOk()
        ->assertSee('Comprobante de Nómina')
        ->assertSee('Aprobado')
        ->assertDontSee('Clasificación IA:')
        ->assertDontSee('Confianza:')
        ->assertDontSee('Nombre detectado:')
        ->assertDontSee('IA: Válido');

    // As admin: AI details must be visible
    $this->actingAs($admin)
        ->get("/portal/loans/{$loan->id}")
        ->assertOk()
        ->assertSee('Clasificación IA:')
        ->assertSee('recibo_nomina')
        ->assertSee('Confianza:')
        ->assertSee('95.0%')
        ->assertSee('Nombre detectado:')
        ->assertSee('Juan Pérez')
        ->assertSee('IA: Válido');

    Livewire::actingAs($admin)
        ->test(ViewLoan::class, ['record' => $loan->getKey()])
        ->assertOk()
        ->assertSee('Clasificación IA:')
        ->assertSee('recibo_nomina')
        ->assertSee('Confianza:')
        ->assertSee('95.0%')
        ->assertSee('Nombre detectado:')
        ->assertSee('Juan Pérez')
        ->assertSee('IA: Válido');

    // As leader: AI details must be visible
    $this->actingAs($lider)
        ->get("/portal/loans/{$loan->id}")
        ->assertOk()
        ->assertSee('Clasificación IA:')
        ->assertSee('recibo_nomina')
        ->assertSee('Confianza:')
        ->assertSee('95.0%')
        ->assertSee('Nombre detectado:')
        ->assertSee('Juan Pérez')
        ->assertSee('IA: Válido');

    Livewire::actingAs($lider)
        ->test(ViewLoan::class, ['record' => $loan->getKey()])
        ->assertOk()
        ->assertSee('Clasificación IA:')
        ->assertSee('recibo_nomina')
        ->assertSee('Confianza:')
        ->assertSee('95.0%')
        ->assertSee('Nombre detectado:')
        ->assertSee('Juan Pérez')
        ->assertSee('IA: Válido');
});

it('configures client-side image compression and webp support on loan evidence uploads', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $benefit = Benefit::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'mixed',
            'benefits' => [
                ['benefit_id' => $benefit->id, 'amount' => 5000],
            ],
        ]);

    $nominaField = $component->instance()->form->getComponent(
        fn ($c): bool => $c instanceof FileUpload && $c->getName() === 'nomina_evidence',
    );

    expect($nominaField)->not->toBeNull()
        ->and($nominaField->getMaxSize())->toBe(20480)
        ->and($nominaField->getAcceptedFileTypes())->toContain('image/webp', 'image/jpeg', 'image/png', 'application/pdf')
        ->and($nominaField->getImageResizeMode())->toBe('contain')
        ->and($nominaField->getImageResizeTargetWidth())->toBe('1920')
        ->and($nominaField->getImageResizeTargetHeight())->toBe('1920')
        ->and($nominaField->shouldAutomaticallyUpscaleImagesWhenResizing())->toBeFalse();

    $benefitFileField = $component->instance()->form->getComponent(
        fn ($c): bool => $c instanceof FileUpload && $c->getName() === 'file',
    );

    expect($benefitFileField)->not->toBeNull()
        ->and($benefitFileField->getMaxSize())->toBe(20480)
        ->and($benefitFileField->getAcceptedFileTypes())->toContain('image/webp', 'image/jpeg', 'image/png', 'application/pdf')
        ->and($benefitFileField->getImageResizeMode())->toBe('contain')
        ->and($benefitFileField->getImageResizeTargetWidth())->toBe('1920')
        ->and($benefitFileField->getImageResizeTargetHeight())->toBe('1920')
        ->and($benefitFileField->shouldAutomaticallyUpscaleImagesWhenResizing())->toBeFalse();
});

it('accepts webp image receipt and persists loan evidence successfully', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->create('recibo_nomina.webp', 200, 'image/webp');
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    Config::set('services.document_ai.enabled', false);

    $this->actingAs($user);

    Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_type' => 'drawn',
            'signature_data' => $dummySig,
            'accept_terms' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var Loan $loan */
    $loan = Loan::where('user_id', $user->id)->firstOrFail();
    $evidence = LoanEvidence::where('loan_id', $loan->id)->where('source_type', 'nomina')->first();

    expect($evidence)->not->toBeNull()
        ->and($evidence->file_name)->toEndWith('.webp')
        ->and($evidence->mime_type)->toBe('image/webp');
});

it('blocks portal access, draft creation, and draft submission during temporalmente fuera de servicio while leaving submitted loans unaffected', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $otherAgremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    $loanService = app(LoanService::class);
    $stateService = app(LoanOperationalStateService::class);

    // Create a draft with signature and evidence while Activo
    $draftLoan = $loanService->createDraft($agremiado, [
        'payment_type' => 'nomina',
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 4,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ]);
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $loanService->signLoan($draftLoan, $dummySig);
    LoanEvidence::create([
        'loan_id' => $draftLoan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Valido,
        'ai_classification' => 'recibo_nomina',
        'ai_confidence' => 0.95,
        'manual_review_status' => 'pending',
    ]);

    // Existing submitted loan for another user
    $submittedLoan = Loan::factory()->for($otherAgremiado)->submitted()->create([
        'payment_type' => 'nomina',
        'requested_amount' => 8000,
        'nomina_amount' => 8000,
        'nomina_fortnights' => 4,
    ]);
    LoanEvidence::create([
        'loan_id' => $submittedLoan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/2/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy2'),
        'file_name' => 'recibo2.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Valido,
        'ai_classification' => 'recibo_nomina',
        'ai_confidence' => 0.95,
        'manual_review_status' => 'approved',
    ]);

    // Admin activates TemporalmenteFueraDeServicio
    $suspensionMessage = 'Suspensión temporal por mantenimiento del fondo.';
    $stateService->updateState($admin, LoanOperationalState::TemporalmenteFueraDeServicio, $suspensionMessage);

    // 1. Portal ListLoans disables createLoan & createLoanEmpty and shows notice
    $this->actingAs($agremiado);
    Livewire::test(ListLoans::class)
        ->assertActionDisabled('createLoan')
        ->assertSee($suspensionMessage);

    // 2. ApplyLoan mount notifies and redirects back to ListLoans
    Livewire::test(ApplyLoan::class)
        ->assertNotified('Servicio temporalmente fuera de servicio')
        ->assertRedirect(ListLoans::getUrl());

    // 3. Draft creation via API returns 422 with suspension message
    $this->actingAs($otherAgremiado)
        ->postJson('/api/prestamos', [
            'payment_type' => 'nomina',
            'requested_amount' => 5000,
            'nomina_amount' => 5000,
            'nomina_fortnights' => 2,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['loan'])
        ->assertJsonPath('errors.loan.0', $suspensionMessage);

    // 4. Draft submission via API returns 422 with suspension message
    $this->actingAs($agremiado)
        ->postJson("/api/prestamos/{$draftLoan->id}/enviar")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['loan'])
        ->assertJsonPath('errors.loan.0', $suspensionMessage);

    expect($draftLoan->fresh()->status)->toBe(LoanStatus::Borrador);

    // 5. Submitted loans (Enviada+) remain unaffected: viewable and reviewable
    $this->actingAs($otherAgremiado)
        ->get("/portal/loans/{$submittedLoan->id}")
        ->assertOk()
        ->assertSee($submittedLoan->folio);

    $reviewedLoan = $loanService->markInReview($submittedLoan, $admin);
    expect($reviewedLoan->status)->toBe(LoanStatus::EnRevision);
});

it('blocks submitting an already-opened ApplyLoan form if operational state becomes temporarily out of service before create', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $user = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $file = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    Config::set('services.document_ai.enabled', false);

    $this->actingAs($user);

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 10000,
            'nomina_fortnights' => 4,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_type' => 'drawn',
            'signature_data' => $dummySig,
            'accept_terms' => true,
        ]);

    // Admin suspends service while user has wizard open
    app(LoanOperationalStateService::class)->updateState(
        $admin,
        LoanOperationalState::TemporalmenteFueraDeServicio,
        'Cierre inmediato por mantenimiento.',
    );

    $component->call('create')
        ->assertNotified();

    expect(Loan::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('persists target fiscal year, generates fiscal-year prefixed folios with independent sequences, and projects benefit collection dates during Cierre de anio', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $user2026 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $user2027 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    $aguinaldo = Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    $loanService = app(LoanService::class);
    $stateService = app(LoanOperationalStateService::class);

    // 1. Create a 2026 loan while Activo
    $loan2026 = $loanService->createDraft($user2026, [
        'payment_type' => 'benefit',
        'requested_amount' => 4000,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
        'benefits' => [
            ['benefit_id' => $aguinaldo->id, 'amount' => 4000],
        ],
    ]);

    expect($loan2026->fiscal_year)->toBe(2026)
        ->and($loan2026->folio)->toBe('SOL-2026-00001')
        ->and($loan2026->loanBenefits()->firstOrFail()->collection_date->format('Y-m-d'))->toBe('2026-12-20');

    // 2. Switch to Cierre de año (target 2027) and create a draft via API
    $stateService->updateState($admin, LoanOperationalState::CierreAnio);

    $response = $this->actingAs($user2027)->postJson('/api/prestamos', [
        'payment_type' => 'mixed',
        'requested_amount' => 15000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 2,
        'bank_name' => 'Santander',
        'bank_account' => '9876543210',
        'benefits' => [
            ['benefit_id' => $aguinaldo->id, 'amount' => 5000],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('loan.fiscal_year', 2027)
        ->assertJsonPath('loan.folio', 'SOL-2027-00001');

    $loan2027 = Loan::findOrFail((int) $response->json('loan.id'));

    expect($loan2027->fiscal_year)->toBe(2027)
        ->and($loan2027->folio)->toBe('SOL-2027-00001')
        ->and($loan2027->first_fortnight_date->format('Y-m-d'))->toBe('2027-01-15')
        ->and($loan2027->loanBenefits()->firstOrFail()->collection_date->format('Y-m-d'))->toBe('2027-12-20');

    // 3. Updating draft keeps 2027 benefit collection date
    $updated2027 = $loanService->updateDraft($loan2027, [
        'requested_amount' => 16000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 2,
        'benefits' => [
            ['benefit_id' => $aguinaldo->id, 'amount' => 6000],
        ],
    ]);

    expect($updated2027->loanBenefits()->firstOrFail()->collection_date->format('Y-m-d'))->toBe('2027-12-20');
});

it('preserves global single-active-loan validation across different fiscal years unless authorized exception applies', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $user = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    // Active loan in 2026
    Loan::factory()->for($user)->submitted()->create([
        'fiscal_year' => 2026,
        'first_fortnight_date' => '2026-12-15',
    ]);

    // Switch to Cierre de año (target 2027)
    app(LoanOperationalStateService::class)->updateState($admin, LoanOperationalState::CierreAnio);

    // Creating a 2027 loan via API must be blocked due to active 2026 loan
    $this->actingAs($user)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 2,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['loan']);

    // Authorized exception still allows creating the 2027 loan
    $exceptionLoan = app(LoanService::class)->createDraft($user, [
        'payment_type' => 'nomina',
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 2,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
        'exception_active_loan' => true,
        'exception_authorized_by' => $admin->id,
    ]);

    expect($exceptionLoan->fiscal_year)->toBe(2027)
        ->and($exceptionLoan->folio)->toBe('SOL-2027-00001')
        ->and($exceptionLoan->exception_active_loan)->toBeTrue();
});

it('blocks submitting drafts from a closed fiscal year while leaving Enviada and later loans unaffected', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $draftOwner = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $submittedOwner = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    $loanService = app(LoanService::class);
    $stateService = app(LoanOperationalStateService::class);

    // 1. Draft created, signed, and with evidence in 2026
    $draft2026 = $loanService->createDraft($draftOwner, [
        'payment_type' => 'nomina',
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 2,
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ]);
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $loanService->signLoan($draft2026, $dummySig);
    LoanEvidence::create([
        'loan_id' => $draft2026->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo-2026.pdf',
        'file_hash' => hash('sha256', 'dummy-2026'),
        'file_name' => 'recibo-2026.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Valido,
        'ai_classification' => 'recibo_nomina',
        'ai_confidence' => 0.95,
        'manual_review_status' => 'approved',
    ]);

    // 2. Another loan already in Enviada for 2026
    $submitted2026 = Loan::factory()->for($submittedOwner)->submitted()->create([
        'fiscal_year' => 2026,
        'payment_type' => 'nomina',
        'requested_amount' => 8000,
        'nomina_amount' => 8000,
        'nomina_fortnights' => 2,
        'first_fortnight_date' => '2026-12-15',
        'monthly_rate' => 6.0,
    ]);
    LoanEvidence::create([
        'loan_id' => $submitted2026->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/2/recibo-2026.pdf',
        'file_hash' => hash('sha256', 'dummy-sub-2026'),
        'file_name' => 'recibo-sub-2026.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Valido,
        'ai_classification' => 'recibo_nomina',
        'ai_confidence' => 0.95,
        'manual_review_status' => 'approved',
    ]);

    // 3. Admin activates Cierre de año (target 2027) -> 2026 is now closed
    $stateService->updateState($admin, LoanOperationalState::CierreAnio);

    // Submitting 2026 draft via API must fail with 422
    $this->actingAs($draftOwner)
        ->postJson("/api/prestamos/{$draft2026->id}/enviar")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['loan']);

    expect($draft2026->fresh()->status)->toBe(LoanStatus::Borrador);

    // 4. Crossing into Jan 1, 2027 (Activo 2027) also keeps 2026 draft blocked
    Carbon::setTestNow('2027-01-05 10:00:00');
    $this->actingAs($draftOwner)
        ->postJson("/api/prestamos/{$draft2026->id}/enviar")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['loan']);

    expect($draft2026->fresh()->status)->toBe(LoanStatus::Borrador);

    // 5. Enviada loan from 2026 continues unaffected (review, approve, disburse)
    $inReview = $loanService->markInReview($submitted2026, $admin);
    expect($inReview->status)->toBe(LoanStatus::EnRevision)
        ->and($inReview->fiscal_year)->toBe(2026);

    $approved = $loanService->approve($inReview, $admin);
    expect($approved->status)->toBe(LoanStatus::Aprobada)
        ->and($approved->fiscal_year)->toBe(2026);

    $disbursed = $loanService->disburse($approved, $admin, [
        'disbursed_at' => '2027-01-05',
        'disbursed_amount' => 8000,
    ]);
    expect($disbursed->status)->toBe(LoanStatus::Desembolsada)
        ->and($disbursed->fiscal_year)->toBe(2026);
});

it('displays Cierre de anio banner in portal ListLoans and ApplyLoan and persists 2027 loan with projected benefit dates', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    Config::set('services.document_ai.enabled', false);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $user = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    $aguinaldo = Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    app(LoanOperationalStateService::class)->updateState($admin, LoanOperationalState::CierreAnio);

    $this->actingAs($user);

    // 1. ListLoans displays Cierre de año banner with target 2027 and enables createLoan
    Livewire::test(ListLoans::class)
        ->assertActionEnabled('createLoan')
        ->assertSee('Cierre de año')
        ->assertSee('2027');

    // 2. ApplyLoan displays Cierre de año banner with target 2027 and submits loan for 2027
    $benefitFile = UploadedFile::fake()->create('aguinaldo.pdf', 100, 'application/pdf');
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    $component = Livewire::test(ApplyLoan::class)
        ->assertSee('Cierre de año')
        ->assertSee('2027')
        ->fillForm([
            'payment_type' => 'benefit',
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
        ])
        ->set('data.benefits', [
            'item-1' => ['benefit_id' => (string) $aguinaldo->id, 'amount' => 5000],
        ]);

    $evidences = $component->get('data.benefit_evidences');
    $evidenceKey = array_key_first($evidences);

    $component->set("data.benefit_evidences.{$evidenceKey}.file", [$benefitFile])
        ->set('data.signature_data', $dummySig)
        ->set('data.accept_terms', true)
        ->call('create')
        ->assertHasNoFormErrors();

    /** @var Loan $loan */
    $loan = Loan::where('user_id', $user->id)->firstOrFail();

    expect($loan->fiscal_year)->toBe(2027)
        ->and($loan->folio)->toBe('SOL-2027-00001')
        ->and($loan->status)->toBe(LoanStatus::Enviada)
        ->and($loan->loanBenefits()->firstOrFail()->collection_date->format('Y-m-d'))->toBe('2027-12-20');
});

it('discards stale simulation token prefill and warns user when fiscal year changed or service is suspended', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $user = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $stateService = app(LoanOperationalStateService::class);

    // 1. Simulate while Activo (2026)
    $sim2026 = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 12000,
            'fortnights' => 2,
            'first_fortnight' => '2026-12-15',
        ],
    ]);
    $sim2026->assertOk();
    $token2026 = basename($sim2026->json('pdf_url'));

    // 2. Admin enables Cierre de año (2027) -> 2026 token is now stale
    $stateService->updateState($admin, LoanOperationalState::CierreAnio);

    $this->actingAs($user);

    Livewire::withQueryParams(['simulation' => $token2026])
        ->test(ApplyLoan::class)
        ->assertNotified('Simulación desactualizada')
        ->assertFormSet([
            'payment_type' => null,
            'nomina_amount' => null,
            'nomina_fortnights' => null,
        ]);

    expect(Cache::has('loan_application_'.$token2026))->toBeFalse();

    // 3. Simulate while Cierre de año (2027) -> valid in Cierre de año, stale if switched back to Activo (2026)
    $sim2027 = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 15000,
            'fortnights' => 4,
            'first_fortnight' => '2027-01-15',
        ],
    ]);
    $sim2027->assertOk();
    $token2027 = basename($sim2027->json('pdf_url'));

    // Valid prefill while still in 2027
    Livewire::withQueryParams(['simulation' => $token2027])
        ->test(ApplyLoan::class)
        ->assertFormSet([
            'payment_type' => 'nomina',
            'nomina_amount' => 15000,
            'nomina_fortnights' => 4,
        ]);

    // Switch back to Activo (2026) -> 2027 token is now stale
    $stateService->updateState($admin, LoanOperationalState::Activo);

    Livewire::withQueryParams(['simulation' => $token2027])
        ->test(ApplyLoan::class)
        ->assertNotified('Simulación desactualizada')
        ->assertFormSet([
            'payment_type' => null,
            'nomina_amount' => null,
            'nomina_fortnights' => null,
        ]);

    // 4. Token created in Activo when service becomes suspended -> redirects with suspension warning and discards prefill
    $simBeforeSuspend = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 9000,
            'fortnights' => 2,
            'first_fortnight' => '2026-12-15',
        ],
    ]);
    $tokenBeforeSuspend = basename($simBeforeSuspend->json('pdf_url'));

    $stateService->updateState($admin, LoanOperationalState::TemporalmenteFueraDeServicio, 'Mantenimiento urgente.');

    Livewire::withQueryParams(['simulation' => $tokenBeforeSuspend])
        ->test(ApplyLoan::class)
        ->assertNotified('Servicio temporalmente fuera de servicio')
        ->assertRedirect(ListLoans::getUrl());

    expect(Cache::has('loan_application_'.$tokenBeforeSuspend))->toBeFalse();
});

it('blocks submitting an already-opened ApplyLoan form if effective fiscal year changes before create', function () {
    Carbon::setTestNow('2026-11-15 10:00:00');
    Config::set('services.document_ai.enabled', false);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $user = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $file = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');
    $dummySig = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    $this->actingAs($user);

    $component = Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'nomina_amount' => 10000,
            'nomina_fortnights' => 2,
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'nomina_evidence' => $file,
            'signature_type' => 'drawn',
            'signature_data' => $dummySig,
            'accept_terms' => true,
        ]);

    // Admin enables Cierre de año (2027) while user still has the 2026 form open
    app(LoanOperationalStateService::class)->updateState($admin, LoanOperationalState::CierreAnio);

    $component->call('create')
        ->assertNotified('Cambio de ejercicio fiscal');

    expect(Loan::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('displays fiscal_year column and filters loans by fiscal_year in ListLoans table', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $user2026 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $user2027 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    $loan2026 = Loan::factory()->for($user2026)->submitted()->create([
        'fiscal_year' => 2026,
        'first_fortnight_date' => '2026-12-15',
    ]);

    $loan2027 = Loan::factory()->for($user2027)->submitted()->create([
        'fiscal_year' => 2027,
        'first_fortnight_date' => '2027-01-15',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListLoans::class)
        ->assertTableColumnExists('fiscal_year')
        ->assertTableFilterExists('fiscal_year')
        ->assertCanSeeTableRecords([$loan2026, $loan2027])
        ->assertTableColumnStateSet('fiscal_year', 2026, record: $loan2026)
        ->assertTableColumnStateSet('fiscal_year', 2027, record: $loan2027)
        ->filterTable('fiscal_year', '2027')
        ->assertCanSeeTableRecords([$loan2027])
        ->assertCanNotSeeTableRecords([$loan2026]);
});

it('adds and backfills fiscal_year reversibly on loans table', function () {
    $user = User::factory()->create();
    $loanFromFortnight = Loan::factory()->for($user)->create([
        'first_fortnight_date' => '2025-07-15',
        'created_at' => '2024-12-20 10:00:00',
    ]);
    $loanFromCreatedAt = Loan::factory()->for($user)->create([
        'first_fortnight_date' => null,
        'created_at' => '2024-05-10 10:00:00',
    ]);

    $migration = require database_path('migrations/2026_11_17_204334_add_fiscal_year_to_loans_table.php');
    $migration->down();

    expect(Schema::hasColumn('loans', 'fiscal_year'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('loans', 'fiscal_year'))->toBeTrue()
        ->and($loanFromFortnight->fresh()->fiscal_year)->toBe(2025)
        ->and($loanFromCreatedAt->fresh()->fiscal_year)->toBe(2024);
});
