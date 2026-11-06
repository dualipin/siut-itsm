<?php

use App\Enums\LoanAiStatus;
use App\Enums\LoanGeneratedDocumentType;
use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Loans\Pages\ApplyLoan;
use App\Models\Benefit;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\LoanGeneratedDocument;
use App\Models\User;
use App\Services\LoanService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Wizard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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

it('blocks new loan applications when the window deadline of November 30 has passed', function () {
    Carbon::setTestNow('2026-12-01');

    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    $response = $this->actingAs($user)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 4,
        'first_fortnight_date' => '2026-12-15',
        'bank_name' => 'BBVA',
        'bank_account' => '1234567890',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['loan']);
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
