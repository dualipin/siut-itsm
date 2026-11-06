<?php

use App\Enums\LoanAiStatus;
use App\Enums\LoanGeneratedDocumentType;
use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Loans\Pages\ApplyLoan;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\LoanGeneratedDocument;
use App\Models\User;
use App\Services\LoanService;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
});

it('allows an authenticated agremiado to submit the Filament loan wizard', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $file = UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf');

    $this->actingAs($user);

    Livewire::test(ApplyLoan::class)
        ->fillForm([
            'payment_type' => 'nomina',
            'monthly_rate' => 6,
            'nomina_amount' => 15000,
            'nomina_fortnights' => 6,
            'first_fortnight_date' => '2026-07-15',
            'bank_name' => 'BBVA',
            'bank_account' => '1234567890',
            'interbank_clabe' => '012180001234567890',
            'nomina_evidence' => $file,
            'accept_terms' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('loans', [
        'user_id' => $user->id,
        'status' => LoanStatus::Enviada->value,
        'requested_amount' => 15000,
    ]);

    $this->assertDatabaseHas('loan_evidences', [
        'source_type' => 'nomina',
    ]);
});

it('stores a loan draft and validates financial inputs', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    $response = $this->actingAs($user)->postJson('/api/prestamos', [
        'payment_type' => 'nomina',
        'requested_amount' => 15000,
        'monthly_rate' => 6.0,
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
    ]);
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
