<?php

use App\Enums\DocumentStatus;
use App\Enums\LoanOperationalState;
use App\Enums\LoanStatus;
use App\Enums\UserDocumentType;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Widgets\AgremiadoAlertsWidget;
use App\Models\AnnualPetition;
use App\Models\Loan;
use App\Models\LoanOperationalSetting;
use App\Models\PetitionRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function attachAllAffiliationDocuments(User $user, DocumentStatus $status = DocumentStatus::Valid): void
{
    foreach (UserDocumentType::cases() as $documentType) {
        $user->addMedia(UploadedFile::fake()->create("{$documentType->value}.pdf", 10, 'application/pdf'))
            ->withCustomProperties(['status' => $status->value])
            ->toMediaCollection($documentType->value);
    }
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('local');
});

test('agremiado al corriente sees no alerts widget container or text on dashboard', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create();
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Valid);

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeFalse();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertDontSee('data-testid="agremiado-alerts-widget"', escape: false);
});

test('agremiado with all five documents uploaded in pending or processing status sees no document alert', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create();
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Pending);

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeFalse();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertDontSee('data-testid="agremiado-alerts-widget"', escape: false);
});

test('admin and leader never see agremiado alerts widget even when system alerts exist', function (): void {
    AnnualPetition::factory()->create([
        'year' => 2026,
        'deadline' => now()->addWeek()->toDateString(),
    ]);

    LoanOperationalSetting::query()->create([
        'state' => LoanOperationalState::TemporalmenteFueraDeServicio,
        'target_fiscal_year' => now()->year,
        'suspension_message' => 'Mantenimiento programado.',
    ]);

    $admin = User::factory()->admin()->active()->create();
    $this->actingAs($admin);
    expect(AgremiadoAlertsWidget::canView())->toBeFalse();
    $this->get('/portal')
        ->assertSuccessful()
        ->assertDontSee('data-testid="agremiado-alerts-widget"', escape: false);

    $leader = User::factory()->lider()->active()->create();
    $this->actingAs($leader);
    expect(AgremiadoAlertsWidget::canView())->toBeFalse();
    $this->get('/portal')
        ->assertSuccessful()
        ->assertDontSee('data-testid="agremiado-alerts-widget"', escape: false);
});

test('agremiado with rejected documents sees alert with link to profile', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create();
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Valid);

    $agremiado->addMedia(UploadedFile::fake()->create('ine-bad.pdf', 10, 'application/pdf'))
        ->withCustomProperties(['status' => DocumentStatus::Invalid->value])
        ->toMediaCollection(UserDocumentType::Ine->value);

    $this->actingAs($agremiado->fresh());

    expect(AgremiadoAlertsWidget::canView())->toBeTrue();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('data-testid="agremiado-alerts-widget"', escape: false)
        ->assertSee('1 documento rechazado')
        ->assertSee(route('filament.portal.auth.profile'), escape: false);
});

test('agremiado with missing required documents sees alert showing missing count and link to profile', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create();

    foreach ([UserDocumentType::Afiliacion, UserDocumentType::Curp] as $type) {
        $agremiado->addMedia(UploadedFile::fake()->create("{$type->value}.pdf", 10, 'application/pdf'))
            ->withCustomProperties(['status' => DocumentStatus::Valid->value])
            ->toMediaCollection($type->value);
    }

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeTrue();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('data-testid="agremiado-alerts-widget"', escape: false)
        ->assertSee('Te faltan 3 documentos obligatorios por subir')
        ->assertSee(route('filament.portal.auth.profile'), escape: false);
});

test('agremiado with both multiple rejected and one missing document sees both alerts', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create();

    foreach ([UserDocumentType::Afiliacion, UserDocumentType::ComprobanteDomicilio] as $type) {
        $agremiado->addMedia(UploadedFile::fake()->create("{$type->value}.pdf", 10, 'application/pdf'))
            ->withCustomProperties(['status' => DocumentStatus::Valid->value])
            ->toMediaCollection($type->value);
    }

    foreach ([UserDocumentType::Ine, UserDocumentType::ComprobantePago] as $type) {
        $agremiado->addMedia(UploadedFile::fake()->create("{$type->value}.pdf", 10, 'application/pdf'))
            ->withCustomProperties(['status' => DocumentStatus::Invalid->value])
            ->toMediaCollection($type->value);
    }

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeTrue();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('2 documentos rechazados')
        ->assertSee('Te falta 1 documento obligatorio por subir');
});

test('agremiado with draft loan sees alert with folio and direct link to loan', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create();
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Valid);

    $draftLoan = Loan::factory()->for($agremiado)->create([
        'status' => LoanStatus::Borrador,
    ]);

    $otherAgremiado = User::factory()->agremiado()->active()->create();
    Loan::factory()->for($otherAgremiado)->create([
        'status' => LoanStatus::Borrador,
    ]);

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeTrue();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('data-testid="agremiado-alerts-widget"', escape: false)
        ->assertSee($draftLoan->folio)
        ->assertSee(LoanResource::getUrl('view', ['record' => $draftLoan]), escape: false);
});

test('agremiado does not see draft alert for non-draft loan or another users draft loan', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create();
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Valid);

    Loan::factory()->for($agremiado)->submitted()->create();

    $otherAgremiado = User::factory()->agremiado()->active()->create();
    $otherDraft = Loan::factory()->for($otherAgremiado)->create([
        'status' => LoanStatus::Borrador,
    ]);

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeFalse();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertDontSee($otherDraft->folio)
        ->assertDontSee('data-testid="agremiado-alerts-widget"', escape: false);
});

test('agremiado sees operational alert when loans are temporarily out of service', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create();
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Valid);

    LoanOperationalSetting::query()->create([
        'state' => LoanOperationalState::TemporalmenteFueraDeServicio,
        'target_fiscal_year' => now()->year,
        'suspension_message' => 'Ventanilla suspendida por auditoría anual.',
    ]);

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeTrue();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('data-testid="agremiado-alerts-widget"', escape: false)
        ->assertSee('Servicio de préstamos temporalmente fuera de servicio')
        ->assertSee('Ventanilla suspendida por auditoría anual.');
});

test('agremiado sees operational alert when loans are in cierre de anio', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create();
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Valid);

    $targetYear = now()->year + 1;

    LoanOperationalSetting::query()->create([
        'state' => LoanOperationalState::CierreAnio,
        'target_fiscal_year' => $targetYear,
        'suspension_message' => null,
    ]);

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeTrue();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('data-testid="agremiado-alerts-widget"', escape: false)
        ->assertSee("Recepción anticipada para el ejercicio fiscal {$targetYear}");
});

test('agremiado sees open annual petition alert with new-tab link when curp has not participated', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create([
        'curp' => 'ABCD900101HDFXYZ01',
    ]);
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Valid);

    $petition = AnnualPetition::factory()->create([
        'year' => 2026,
        'deadline' => now()->toDateString(),
    ]);

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeTrue();

    $expectedUrl = route('petitions.index', ['annualPetition' => $petition]);

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('data-testid="agremiado-alerts-widget"', escape: false)
        ->assertSee("Pliego Anual {$petition->year}")
        ->assertSee($expectedUrl, escape: false)
        ->assertSee('target="_blank"', escape: false);
});

test('agremiado does not see annual petition alert when curp already submitted a proposal or petition is expired or curp is empty', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create([
        'curp' => 'ABCD900101HDFXYZ02',
    ]);
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Valid);

    $petition = AnnualPetition::factory()->create([
        'year' => 2026,
        'deadline' => now()->addDays(5)->toDateString(),
    ]);

    PetitionRequest::factory()->create([
        'annual_petition_id' => $petition->id,
        'curp' => 'abcd900101hdfxyz02',
    ]);

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeFalse();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertDontSee('data-testid="agremiado-alerts-widget"', escape: false);

    // Expired petition without participation should also remain hidden
    $petition->delete();
    AnnualPetition::factory()->expired()->create([
        'year' => 2025,
    ]);

    expect(AgremiadoAlertsWidget::canView())->toBeFalse();

    // Open petition with agremiado having null CURP should remain hidden
    AnnualPetition::factory()->create([
        'year' => 2027,
        'deadline' => now()->addDays(5)->toDateString(),
    ]);

    $agremiadoWithoutCurp = User::factory()->agremiado()->active()->create([
        'curp' => null,
    ]);
    attachAllAffiliationDocuments($agremiadoWithoutCurp, DocumentStatus::Valid);

    $this->actingAs($agremiadoWithoutCurp);

    expect(AgremiadoAlertsWidget::canView())->toBeFalse();
});

test('agremiado sees second open annual petition alert when they already participated in the earlier one', function (): void {
    $agremiado = User::factory()->agremiado()->active()->create([
        'curp' => 'ABCD900101HDFXYZ03',
    ]);
    attachAllAffiliationDocuments($agremiado, DocumentStatus::Valid);

    $earlierPetition = AnnualPetition::factory()->create([
        'year' => 2026,
        'deadline' => now()->addDays(2)->toDateString(),
    ]);

    $laterPetition = AnnualPetition::factory()->create([
        'year' => 2027,
        'deadline' => now()->addDays(10)->toDateString(),
    ]);

    PetitionRequest::factory()->create([
        'annual_petition_id' => $earlierPetition->id,
        'curp' => 'abcd900101hdfxyz03',
    ]);

    $this->actingAs($agremiado);

    expect(AgremiadoAlertsWidget::canView())->toBeTrue();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('data-testid="agremiado-alerts-widget"', escape: false)
        ->assertSee("Pliego Anual {$laterPetition->year}")
        ->assertSee(route('petitions.index', ['annualPetition' => $laterPetition]), escape: false)
        ->assertDontSee("Pliego Anual {$earlierPetition->year}");
});
