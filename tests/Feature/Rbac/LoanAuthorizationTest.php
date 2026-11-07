<?php

use App\Enums\LoanGeneratedDocumentType;
use App\Enums\LoanPaymentType;
use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\LoanGeneratedDocument;
use App\Models\User;
use App\Policies\LoanPolicy;
use App\Services\LoanService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('local');
});

test('agremiado can only view their own loans and receives 403 on another users loan', function () {
    $agremiado1 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $agremiado2 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    $loan1 = Loan::factory()->create(['user_id' => $agremiado1->id, 'status' => LoanStatus::Enviada]);
    $loan2 = Loan::factory()->create(['user_id' => $agremiado2->id, 'status' => LoanStatus::Enviada]);

    $this->actingAs($agremiado1);

    // Agremiado1 can view loan1
    $this->get("/portal/loans/{$loan1->id}")->assertSuccessful();

    // Agremiado1 gets 403 or 404 (scoped out) on loan2
    $response = $this->get("/portal/loans/{$loan2->id}");
    expect(in_array($response->status(), [403, 404], true))->toBeTrue();
});

test('agremiado only downloads documents and evidences belonging to their own loan', function () {
    $agremiado1 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $agremiado2 = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    $loan1 = Loan::factory()->create(['user_id' => $agremiado1->id]);
    $loan2 = Loan::factory()->create(['user_id' => $agremiado2->id]);

    Storage::disk('local')->put('private/loans/doc1.pdf', '%PDF-1.4 sample');
    $doc1 = LoanGeneratedDocument::create([
        'loan_id' => $loan1->id,
        'document_type' => LoanGeneratedDocumentType::Solicitud,
        'file_path' => 'private/loans/doc1.pdf',
        'file_hash' => hash('sha256', '%PDF-1.4 sample'),
        'file_name' => 'doc1.pdf',
        'file_size' => 100,
        'mime_type' => 'application/pdf',
        'version' => 1,
        'generated_at' => now(),
    ]);

    Storage::disk('local')->put('private/loans/doc2.pdf', '%PDF-1.4 sample');
    $doc2 = LoanGeneratedDocument::create([
        'loan_id' => $loan2->id,
        'document_type' => LoanGeneratedDocumentType::Solicitud,
        'file_path' => 'private/loans/doc2.pdf',
        'file_hash' => hash('sha256', '%PDF-1.4 sample'),
        'file_name' => 'doc2.pdf',
        'file_size' => 100,
        'mime_type' => 'application/pdf',
        'version' => 1,
        'generated_at' => now(),
    ]);

    $this->actingAs($agremiado1);

    // Can download own document
    $this->get(route('loans.documents.download', ['loan' => $loan1, 'document' => $doc1]))
        ->assertSuccessful();

    // Cannot download other user's document
    $this->get(route('loans.documents.download', ['loan' => $loan2, 'document' => $doc2]))
        ->assertForbidden();
});

test('soft-deleted loans are completely hidden from agremiado in eloquent query', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $activeLoan = Loan::factory()->create(['user_id' => $agremiado->id, 'folio' => 'ACTIVE-001']);
    $deletedLoan = Loan::factory()->create(['user_id' => $agremiado->id, 'folio' => 'DELETED-001']);
    $deletedLoan->delete();

    $this->actingAs($agremiado);

    $results = LoanResource::getEloquentQuery()->get();
    expect($results->pluck('id'))->toContain($activeLoan->id)
        ->and($results->pluck('id'))->not->toContain($deletedLoan->id);

    // Admin CAN see soft-deleted loans
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);
    $adminResults = LoanResource::getEloquentQuery()->get();
    expect($adminResults->pluck('id'))->toContain($deletedLoan->id);
});

test('separation of duties prevents admin and leader from reviewing or disbursing their own loan in policy and service', function () {
    $adminReviewer = User::factory()->admin()->create(['is_active' => true]);
    $adminApplicant = User::factory()->admin()->create(['is_active' => true]);

    $ownLoan = Loan::factory()->create([
        'user_id' => $adminReviewer->id,
        'status' => LoanStatus::Enviada,
        'payment_type' => LoanPaymentType::Nomina,
    ]);

    $otherLoan = Loan::factory()->create([
        'user_id' => $adminApplicant->id,
        'status' => LoanStatus::Enviada,
        'payment_type' => LoanPaymentType::Nomina,
    ]);

    $policy = new LoanPolicy;
    $service = app(LoanService::class);

    // Policy checks
    expect($policy->review($adminReviewer, $otherLoan))->toBeTrue('Admin can review other user loan');
    expect($policy->review($adminReviewer, $ownLoan))->toBeFalse('Admin cannot review own loan');

    // Service checks: reviewing own loan throws AuthorizationException
    expect(fn () => $service->markInReview($ownLoan, $adminReviewer))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $service->approve($ownLoan, $adminReviewer))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $service->waitlist($ownLoan, $adminReviewer, 'Motivo de espera'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => $service->reject($ownLoan, $adminReviewer, 'Motivo de rechazo'))
        ->toThrow(AuthorizationException::class);

    // Disbursing own loan
    $ownApprovedLoan = Loan::factory()->create([
        'user_id' => $adminReviewer->id,
        'status' => LoanStatus::Aprobada,
        'payment_type' => LoanPaymentType::Nomina,
        'approved_amount' => 10000,
    ]);

    expect($policy->disburse($adminReviewer, $ownApprovedLoan))->toBeFalse();
    expect(fn () => $service->disburse($ownApprovedLoan, $adminReviewer, ['disbursed_at' => now()->format('Y-m-d')]))
        ->toThrow(AuthorizationException::class);

    // Validating evidence on own loan
    $evidence = LoanEvidence::create([
        'loan_id' => $ownLoan->id,
        'source_type' => 'benefit',
        'file_path' => 'evidences/sample.pdf',
        'file_hash' => hash('sha256', 'sample'),
        'file_name' => 'sample.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'manual_review_status' => 'pending',
    ]);
    expect(fn () => $service->reviewEvidence($evidence, $adminReviewer, 'approved'))
        ->toThrow(AuthorizationException::class);
});

test('view loan page hides review actions when admin views their own loan', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $ownLoan = Loan::factory()->create([
        'user_id' => $admin->id,
        'status' => LoanStatus::Enviada,
        'payment_type' => LoanPaymentType::Nomina,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewLoan::class, ['record' => $ownLoan->getRouteKey()])
        ->assertActionHidden('approveLoan')
        ->assertActionHidden('waitlistLoan')
        ->assertActionHidden('rejectLoan')
        ->assertActionHidden('validateEvidence');
});
