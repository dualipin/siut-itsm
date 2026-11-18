<?php

use App\Enums\LoanGeneratedDocumentType;
use App\Enums\LoanOperationalState;
use App\Enums\LoanPaymentType;
use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\Loans\Pages\ListLoans;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\LoanGeneratedDocument;
use App\Models\LoanOperationalSetting;
use App\Models\User;
use App\Policies\LoanPolicy;
use App\Services\LoanOperationalStateService;
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

test('admin and leader can update loan operational state with audit metadata while agremiado is forbidden', function () {
    $this->travelTo('2026-06-15 10:30:00');

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $lider = User::factory()->create(['role' => UserRole::Lider, 'is_active' => true]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    $service = app(LoanOperationalStateService::class);
    $policy = new LoanPolicy;

    expect($service->effectiveState())->toBe(LoanOperationalState::Activo)
        ->and($service->effectiveFiscalYear())->toBe(2026);

    expect($policy->manageOperationalState($admin))->toBeTrue()
        ->and($policy->manageOperationalState($lider))->toBeTrue()
        ->and($policy->manageOperationalState($agremiado))->toBeFalse();

    // Agremiado is rejected at the service boundary
    expect(fn () => $service->updateState($agremiado, LoanOperationalState::TemporalmenteFueraDeServicio, 'Intento no autorizado'))
        ->toThrow(AuthorizationException::class);

    expect(LoanOperationalSetting::query()->count())->toBe(0);

    // Admin sets TemporalmenteFueraDeServicio with custom message
    $setting = $service->updateState($admin, LoanOperationalState::TemporalmenteFueraDeServicio, 'Mantenimiento programado de auditoría.');

    expect($setting->state)->toBe(LoanOperationalState::TemporalmenteFueraDeServicio)
        ->and($setting->target_fiscal_year)->toBe(2026)
        ->and($setting->suspension_message)->toBe('Mantenimiento programado de auditoría.')
        ->and($setting->updated_by)->toBe($admin->id)
        ->and($setting->state_changed_at?->format('Y-m-d H:i:s'))->toBe('2026-06-15 10:30:00')
        ->and($service->publicSuspensionMessage())->toBe('Mantenimiento programado de auditoría.');

    // Leader sets TemporalmenteFueraDeServicio with blank message -> default institutional message
    $this->travelTo('2026-06-16 09:00:00');
    $setting = $service->updateState($lider, LoanOperationalState::TemporalmenteFueraDeServicio, '   ');

    expect($setting->suspension_message)->toBeNull()
        ->and($setting->updated_by)->toBe($lider->id)
        ->and($setting->state_changed_at?->format('Y-m-d H:i:s'))->toBe('2026-06-16 09:00:00')
        ->and($service->publicSuspensionMessage())->toBe(LoanOperationalStateService::DEFAULT_SUSPENSION_MESSAGE);

    // Leader switches to CierreAnio -> target_fiscal_year automatically becomes year + 1
    $this->travelTo('2026-12-02 12:00:00');
    $setting = $service->updateState($lider, LoanOperationalState::CierreAnio);

    expect($setting->state)->toBe(LoanOperationalState::CierreAnio)
        ->and($setting->target_fiscal_year)->toBe(2027)
        ->and($setting->suspension_message)->toBeNull()
        ->and($setting->updated_by)->toBe($lider->id);
});

test('list loans exposes operational state governance action to admin and leader and hides it from agremiado', function () {
    $this->travelTo('2026-06-15 11:00:00');

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $lider = User::factory()->create(['role' => UserRole::Lider, 'is_active' => true]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    // Agremiado cannot see or execute operational state action
    $this->actingAs($agremiado);
    Livewire::test(ListLoans::class)
        ->assertActionHidden('manageOperationalState');

    // Admin can open and change operational state to TemporalmenteFueraDeServicio with custom message
    $this->actingAs($admin);
    Livewire::test(ListLoans::class)
        ->assertActionVisible('manageOperationalState')
        ->callAction('manageOperationalState', [
            'state' => LoanOperationalState::TemporalmenteFueraDeServicio->value,
            'suspension_message' => 'Suspensión temporal por revisión interna.',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified()
        ->assertSee('Suspensión temporal por revisión interna.');

    $setting = LoanOperationalSetting::query()->firstOrFail();
    expect($setting->state)->toBe(LoanOperationalState::TemporalmenteFueraDeServicio)
        ->and($setting->suspension_message)->toBe('Suspensión temporal por revisión interna.')
        ->and($setting->updated_by)->toBe($admin->id)
        ->and($setting->state_changed_at?->format('Y-m-d H:i:s'))->toBe('2026-06-15 11:00:00');

    // Agremiado sees suspension banner and disabled createLoan button on ListLoans
    $this->actingAs($agremiado);
    Livewire::test(ListLoans::class)
        ->assertActionHidden('manageOperationalState')
        ->assertActionDisabled('createLoan')
        ->assertSee('Suspensión temporal por revisión interna.');

    // Leader can restore operational state back to Activo
    $this->travelTo('2026-06-15 15:30:00');
    $this->actingAs($lider);
    Livewire::test(ListLoans::class)
        ->assertActionVisible('manageOperationalState')
        ->callAction('manageOperationalState', [
            'state' => LoanOperationalState::Activo->value,
            'suspension_message' => null,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    $setting->refresh();
    expect($setting->state)->toBe(LoanOperationalState::Activo)
        ->and($setting->suspension_message)->toBeNull()
        ->and($setting->updated_by)->toBe($lider->id)
        ->and($setting->state_changed_at?->format('Y-m-d H:i:s'))->toBe('2026-06-15 15:30:00');
});

test('cierre de anio locks next fiscal year with modal preview and auto-transitions to activo on january 1 without skipping a year', function () {
    $this->travelTo('2026-11-20 14:00:00');

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $stateService = app(LoanOperationalStateService::class);

    $this->actingAs($admin);

    // Selecting Cierre de año in modal shows explicit preview for 2027 and locks target_fiscal_year = 2027
    Livewire::test(ListLoans::class)
        ->mountAction('manageOperationalState')
        ->assertSchemaComponentHidden('cierre_anio_preview')
        ->assertSchemaComponentDoesNotExist('target_fiscal_year')
        ->setActionData([
            'state' => LoanOperationalState::CierreAnio->value,
        ])
        ->assertSchemaComponentVisible('cierre_anio_preview')
        ->assertSchemaComponentExists('cierre_anio_preview', checkComponentUsing: function ($component): bool {
            $content = (string) $component->getContent();

            return str_contains($content, '2027') && str_contains($content, 'sin captura manual');
        })
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified()
        ->assertSee('Estado: Cierre de año');

    $setting = LoanOperationalSetting::query()->firstOrFail();
    expect($setting->state)->toBe(LoanOperationalState::CierreAnio)
        ->and($setting->target_fiscal_year)->toBe(2027)
        ->and($stateService->effectiveState())->toBe(LoanOperationalState::CierreAnio)
        ->and($stateService->effectiveFiscalYear())->toBe(2027);

    // Crossing to Jan 1, 2027 auto-transitions effective state to Activo for 2027 (never jumping to 2028)
    $this->travelTo('2027-01-01 00:00:05');

    expect($stateService->effectiveState())->toBe(LoanOperationalState::Activo)
        ->and($stateService->effectiveFiscalYear())->toBe(2027);

    Livewire::test(ListLoans::class)
        ->assertSee('Estado: Activo')
        ->mountAction('manageOperationalState')
        ->assertActionDataSet([
            'state' => LoanOperationalState::Activo,
        ]);

    // Switching explicitly back to Activo in Dec 2026 resets target_fiscal_year to 2026
    $this->travelTo('2026-12-10 10:00:00');
    $setting = $stateService->updateState($admin, LoanOperationalState::Activo);
    expect($setting->target_fiscal_year)->toBe(2026);
});
