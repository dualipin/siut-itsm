<?php

use App\Enums\LoanAiStatus;
use App\Enums\LoanPaymentType;
use App\Enums\UserRole;
use App\Filament\Resources\Loans\Pages\ReviewLoanDocuments;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($this->admin);
});

test('admin can access review loan documents page', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
        'payment_type' => LoanPaymentType::Nomina,
    ]);

    LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'ai_status' => LoanAiStatus::Valido,
        'ai_confidence' => 0.95,
        'manual_review_status' => 'pending',
    ]);

    $this->get(route('filament.portal.resources.loans.review-documents', ['record' => $loan]))
        ->assertSuccessful()
        ->assertSee('Expediente del Préstamo')
        ->assertSee('Datos a Cotejar')
        ->assertSee('Comprobante de Nómina');
});

test('unauthorized agremiado cannot access review loan documents page', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
    ]);

    $this->actingAs($agremiado)
        ->get(route('filament.portal.resources.loans.review-documents', ['record' => $loan]))
        ->assertForbidden();
});

test('review loan documents page mounts with active evidence id', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
    ]);

    $evidence = LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'ai_status' => LoanAiStatus::Valido,
        'ai_confidence' => 0.95,
        'manual_review_status' => 'pending',
    ]);

    Livewire::test(ReviewLoanDocuments::class, ['record' => $loan->getRouteKey()])
        ->assertSet('activeEvidenceId', $evidence->id)
        ->assertActionExists('approveEvidence')
        ->assertActionExists('rejectEvidence')
        ->assertActionExists('resetEvidence')
        ->assertActionExists('analyzeWithAi')
        ->assertActionExists('replaceEvidence');
});

test('admin can approve an evidence', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
    ]);

    $evidence = LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'ai_status' => LoanAiStatus::Dudoso,
        'manual_review_status' => 'pending',
    ]);

    expect($evidence->manual_review_status)->toBe('pending');

    Livewire::test(ReviewLoanDocuments::class, ['record' => $loan->getRouteKey()])
        ->callAction('approveEvidence', arguments: ['id' => $evidence->id]);

    $evidence->refresh();
    expect($evidence->manual_review_status)->toBe('approved')
        ->and($evidence->reviewed_by)->toBe($this->admin->id)
        ->and($evidence->reviewed_at)->not->toBeNull();
});

test('admin can reject an evidence with notes', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
    ]);

    $evidence = LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'ai_status' => LoanAiStatus::Invalido,
        'manual_review_status' => 'pending',
    ]);

    Livewire::test(ReviewLoanDocuments::class, ['record' => $loan->getRouteKey()])
        ->callAction('rejectEvidence', data: [
            'notes' => 'El recibo de nómina corresponde a un periodo anterior.',
        ], arguments: ['id' => $evidence->id]);

    $evidence->refresh();
    expect($evidence->manual_review_status)->toBe('rejected')
        ->and($evidence->manual_review_notes)->toBe('El recibo de nómina corresponde a un periodo anterior.')
        ->and($evidence->reviewed_by)->toBe($this->admin->id);
});

test('admin can reset evidence to pending', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
    ]);

    $evidence = LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'manual_review_status' => 'rejected',
        'manual_review_notes' => 'Observación previa',
    ]);

    Livewire::test(ReviewLoanDocuments::class, ['record' => $loan->getRouteKey()])
        ->callAction('resetEvidence', arguments: ['id' => $evidence->id]);

    $evidence->refresh();
    expect($evidence->manual_review_status)->toBe('pending')
        ->and($evidence->manual_review_notes)->toBeNull();
});

test('admin can approve all evidences from header action', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
    ]);

    $ev1 = LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo1.pdf',
        'file_hash' => hash('sha256', '1'),
        'file_name' => 'recibo1.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'manual_review_status' => 'pending',
    ]);

    $ev2 = LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'prestacion',
        'file_path' => 'loans/1/prestacion.pdf',
        'file_hash' => hash('sha256', '2'),
        'file_name' => 'prestacion.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'manual_review_status' => 'pending',
    ]);

    Livewire::test(ReviewLoanDocuments::class, ['record' => $loan->getRouteKey()])
        ->callAction('approveAllEvidences');

    expect($ev1->fresh()->manual_review_status)->toBe('approved')
        ->and($ev2->fresh()->manual_review_status)->toBe('approved');
});

test('view loan page has validateEvidence action pointing to review-documents', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
    ]);

    LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo1.pdf',
        'file_hash' => hash('sha256', '1'),
        'file_name' => 'recibo1.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'manual_review_status' => 'pending',
    ]);

    Livewire::test(ViewLoan::class, ['record' => $loan->getRouteKey()])
        ->assertActionExists('validateEvidence');

    $this->get(route('filament.portal.resources.loans.view', ['record' => $loan]))
        ->assertSuccessful()
        ->assertSee('Validar Evidencia Manual')
        ->assertSee(route('filament.portal.resources.loans.review-documents', ['record' => $loan]));
});
