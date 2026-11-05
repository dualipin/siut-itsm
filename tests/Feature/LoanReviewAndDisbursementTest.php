<?php

use App\Enums\DisbursementMethod;
use App\Enums\LoanAiStatus;
use App\Enums\LoanGeneratedDocumentType;
use App\Enums\LoanPaymentType;
use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Exceptions\LoanTransitionException;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\LoanGeneratedDocument;
use App\Models\LoanStatusHistory;
use App\Models\User;
use App\Policies\LoanPolicy;
use App\Services\LoanPdfGenerationService;
use App\Services\LoanService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Carbon::setTestNow('2026-06-01');
    Storage::fake('local');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('prohibits standard agremiados from reviewing, approving, waitlisting, rejecting, or disbursing loans', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->create([
        'user_id' => $agremiado->id,
        'status' => LoanStatus::Enviada,
    ]);

    $policy = new LoanPolicy;

    expect($policy->review($agremiado, $loan))->toBeFalse()
        ->and($policy->review($admin, $loan))->toBeTrue()
        ->and($policy->disburse($agremiado, $loan))->toBeFalse()
        ->and($policy->regenerateDocuments($agremiado, $loan))->toBeFalse()
        ->and($policy->regenerateDocuments($admin, $loan))->toBeTrue();
});

it('allows leader or admin to approve a submitted loan with complete valid evidence', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
        'payment_type' => LoanPaymentType::Nomina,
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 4,
        'first_fortnight_date' => '2026-06-30',
        'monthly_rate' => 6.0,
    ]);

    // Attach valid evidence for nomina
    LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/evidence-nomina.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Valido,
        'ai_confidence' => 0.95,
        'manual_review_status' => 'pending',
    ]);

    /** @var LoanService $service */
    $service = app(LoanService::class);

    $approved = $service->approve($loan, $admin, [
        'approved_amount' => 10000,
        'monthly_rate' => 6.0,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 4,
        'comment' => 'Expediente completo y validado.',
    ]);

    expect($approved->status)->toBe(LoanStatus::Aprobada)
        ->and($approved->approved_amount)->toEqual(10000)
        ->and($approved->reviewed_by)->toBe($admin->id)
        ->and($approved->reviewed_at)->not->toBeNull();

    // Verify amortization rows were created (1 extra_days + 4 fortnights = 5)
    expect($approved->amortizationRows()->count())->toBe(5)
        ->and($approved->amortizationRows()->where('type', 'nomina')->count())->toBe(4);

    // Verify financial summary was created
    expect($approved->financialSummary)->not->toBeNull()
        ->and($approved->financialSummary->total_principal)->toEqual(10000);

    // Verify status history
    $history = LoanStatusHistory::where('loan_id', $loan->id)->latest('id')->first();
    expect($history)->not->toBeNull()
        ->and($history->from_status)->toBe(LoanStatus::Enviada)
        ->and($history->to_status)->toBe(LoanStatus::Aprobada)
        ->and($history->user_id)->toBe($admin->id);

    // Verify generated documents
    expect($approved->generatedDocuments()->count())->toBe(4);
    foreach (LoanGeneratedDocumentType::cases() as $type) {
        $doc = $approved->generatedDocuments()->where('document_type', $type)->first();
        expect($doc)->not->toBeNull()
            ->and(Storage::disk('local')->exists($doc->file_path))->toBeTrue();
    }
});

it('cannot approve a loan if evidence is invalid unless manually approved', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
        'payment_type' => LoanPaymentType::Nomina,
        'requested_amount' => 10000,
        'nomina_amount' => 10000,
        'nomina_fortnights' => 4,
        'first_fortnight_date' => '2026-06-30',
    ]);

    // Attach invalid AI evidence
    $evidence = LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/evidence-nomina.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Invalido,
        'ai_confidence' => 0.10,
        'manual_review_status' => 'pending',
    ]);

    /** @var LoanService $service */
    $service = app(LoanService::class);

    // Approval must fail due to incomplete / invalid evidence
    expect(fn () => $service->approve($loan, $admin))
        ->toThrow(ValidationException::class);

    // Admin manually reviews and approves the evidence
    $service->reviewEvidence($evidence, $admin, 'approved', 'Revisado físicamente en sindicato.');

    $evidence->refresh();
    expect($evidence->manual_review_status)->toBe('approved')
        ->and($evidence->isAccepted())->toBeTrue();

    // Now approval succeeds
    $approved = $service->approve($loan, $admin);
    expect($approved->status)->toBe(LoanStatus::Aprobada);
});

it('allows leader or admin to reject a loan with reason', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
    ]);

    /** @var LoanService $service */
    $service = app(LoanService::class);

    $rejected = $service->reject($loan, $admin, 'Documentación apócrifa.');

    expect($rejected->status)->toBe(LoanStatus::Rechazada)
        ->and($rejected->rejection_reason)->toBe('Documentación apócrifa.')
        ->and($rejected->reviewed_by)->toBe($admin->id);

    $history = LoanStatusHistory::where('loan_id', $loan->id)->latest('id')->first();
    expect($history->to_status)->toBe(LoanStatus::Rechazada)
        ->and($history->comment)->toBe('Documentación apócrifa.');
});

it('allows leader or admin to place a loan on waitlist and then approve it', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
        'payment_type' => LoanPaymentType::Nomina,
        'requested_amount' => 5000,
        'nomina_amount' => 5000,
        'nomina_fortnights' => 2,
        'first_fortnight_date' => '2026-06-30',
    ]);

    LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'nomina',
        'file_path' => 'loans/1/recibo.pdf',
        'file_hash' => hash('sha256', 'dummy'),
        'file_name' => 'recibo.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'ai_status' => LoanAiStatus::Valido,
        'manual_review_status' => 'pending',
    ]);

    /** @var LoanService $service */
    $service = app(LoanService::class);

    $waitlisted = $service->waitlist($loan, $admin, 'Fondos agotados temporalmente.');
    expect($waitlisted->status)->toBe(LoanStatus::ListaEspera)
        ->and($waitlisted->waitlist_reason)->toBe('Fondos agotados temporalmente.');

    // Approval from waitlist is allowed
    $approved = $service->approve($waitlisted, $admin);
    expect($approved->status)->toBe(LoanStatus::Aprobada);
});

it('throws exception when transitioning an already decided loan', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->approved()->create([
        'user_id' => $agremiado->id,
    ]);

    /** @var LoanService $service */
    $service = app(LoanService::class);

    // Cannot approve an already approved loan
    expect(fn () => $service->approve($loan, $admin))
        ->toThrow(LoanTransitionException::class);

    // Cannot reject an already approved loan
    expect(fn () => $service->reject($loan, $admin, 'Demasiado tarde'))
        ->toThrow(LoanTransitionException::class);

    // Cannot waitlist an already approved loan
    expect(fn () => $service->waitlist($loan, $admin, 'No aplica'))
        ->toThrow(LoanTransitionException::class);
});

it('allows leader or admin to disburse an approved loan only once', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->approved()->create([
        'user_id' => $agremiado->id,
        'payment_type' => LoanPaymentType::Nomina,
        'approved_amount' => 8000,
        'nomina_amount' => 8000,
        'nomina_fortnights' => 4,
        'first_fortnight_date' => '2026-06-30',
        'monthly_rate' => 6.0,
    ]);

    /** @var LoanService $service */
    $service = app(LoanService::class);

    $disbursed = $service->disburse($loan, $admin, [
        'disbursed_at' => '2026-06-15',
        'disbursed_amount' => 8000,
        'disbursement_method' => 'transferencia',
        'disbursement_reference' => 'SPEI-884422',
    ]);

    expect($disbursed->status)->toBe(LoanStatus::Desembolsada)
        ->and($disbursed->disbursed_at->format('Y-m-d'))->toBe('2026-06-15')
        ->and($disbursed->disbursed_amount)->toEqual(8000)
        ->and($disbursed->disbursement_method)->toBe(DisbursementMethod::Transferencia)
        ->and($disbursed->disbursement_reference)->toBe('SPEI-884422')
        ->and($disbursed->disbursed_by)->toBe($admin->id);

    // Verify status history
    $history = LoanStatusHistory::where('loan_id', $loan->id)->latest('id')->first();
    expect($history->to_status)->toBe(LoanStatus::Desembolsada)
        ->and($history->user_id)->toBe($admin->id);

    // Attempting to disburse again must fail
    expect(fn () => $service->disburse($loan, $admin, [
        'disbursed_at' => '2026-06-16',
        'disbursed_amount' => 8000,
    ]))->toThrow(LoanTransitionException::class);
});

it('allows manual document regeneration', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->approved()->create([
        'user_id' => $agremiado->id,
        'payment_type' => LoanPaymentType::Nomina,
        'approved_amount' => 6000,
        'nomina_amount' => 6000,
        'nomina_fortnights' => 2,
        'first_fortnight_date' => '2026-06-30',
    ]);

    /** @var LoanPdfGenerationService $pdfService */
    $pdfService = app(LoanPdfGenerationService::class);

    $pdfService->generateAllDocuments($loan, $admin);

    $v1Count = LoanGeneratedDocument::where('loan_id', $loan->id)->where('version', 1)->count();
    expect($v1Count)->toBe(4);

    // Regenerate -> creates version 2
    $pdfService->generateAllDocuments($loan, $admin);

    $v2Count = LoanGeneratedDocument::where('loan_id', $loan->id)->where('version', 2)->count();
    expect($v2Count)->toBe(4);
});

it('enforces authorization on private document download routes', function () {
    $userA = User::factory()->create(['role' => UserRole::Agremiado]);
    $userB = User::factory()->create(['role' => UserRole::Agremiado]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $loan = Loan::factory()->approved()->create([
        'user_id' => $userA->id,
    ]);

    $filePath = "loans/{$loan->id}/generated/test.pdf";
    Storage::disk('local')->put($filePath, 'PDF content');

    $doc = LoanGeneratedDocument::create([
        'loan_id' => $loan->id,
        'document_type' => LoanGeneratedDocumentType::Solicitud,
        'version' => 1,
        'file_path' => $filePath,
        'file_hash' => hash('sha256', 'PDF content'),
        'file_size' => strlen('PDF content'),
        'generated_by' => $admin->id,
        'generated_at' => now(),
    ]);

    // Stranger (userB) cannot download userA's loan documents (403)
    $this->actingAs($userB)
        ->get("/portal/prestamos/{$loan->id}/documentos/{$doc->id}")
        ->assertForbidden();

    // Owner (userA) can download their own documents
    $this->actingAs($userA)
        ->get("/portal/prestamos/{$loan->id}/documentos/{$doc->id}")
        ->assertOk();

    // Admin can download any user's documents
    $this->actingAs($admin)
        ->get("/portal/prestamos/{$loan->id}/documentos/{$doc->id}")
        ->assertOk();
});
