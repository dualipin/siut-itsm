<?php

use App\Actions\GenerateUserCredentialPdf;
use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Filament\Pages\Profile;
use App\Filament\Resources\Users\Pages\ReviewDocuments;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\StreamedResponse;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('it generates a valid PDF download response for user with complete data', function () {
    $user = User::factory()->create([
        'name' => 'Juan',
        'surnames' => 'Perez Lopez',
        'curp' => 'PELJ900101HDFRRN01',
        'nss' => '12345678901',
        'category' => 'Profesor de Tiempo Completo',
        'hiring_date' => now()->subYears(2),
        'role' => UserRole::Agremiado,
    ]);

    /** @var GenerateUserCredentialPdf $action */
    $action = app(GenerateUserCredentialPdf::class);
    $response = $action->execute($user);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('credencial-PELJ900101HDFRRN01.pdf');

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect(strlen($content))->toBeGreaterThan(1000);
});

test('it generates a valid PDF download response for user with missing optional attributes', function () {
    $user = User::factory()->create([
        'name' => 'Maria',
        'surnames' => 'Santos',
        'curp' => null,
        'nss' => null,
        'category' => null,
        'hiring_date' => null,
        'photo_path' => null,
        'role' => UserRole::Agremiado,
    ]);

    /** @var GenerateUserCredentialPdf $action */
    $action = app(GenerateUserCredentialPdf::class);
    $response = $action->execute($user);

    expect($response)->toBeInstanceOf(StreamedResponse::class)
        ->and($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain("credencial-usuario-{$user->id}.pdf");

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect(strlen($content))->toBeGreaterThan(1000);
});

test('credential pdf front matches the original credential layout', function () {
    $user = User::factory()->create([
        'name' => 'Angel',
        'surnames' => 'Leon Ramos',
        'curp' => 'LERM800101HDFRRN01',
        'nss' => '83917114858',
        'category' => 'Docente',
        'hiring_date' => now()->subYears(2),
        'role' => UserRole::Agremiado,
    ]);

    /** @var GenerateUserCredentialPdf $action */
    $action = app(GenerateUserCredentialPdf::class);
    $response = $action->execute($user);

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    $text = (new Parser)->parseContent($content)->getText();

    expect($text)
        ->toContain('ANGEL LEON RAMOS')
        ->toContain('DOCENTE')
        ->toContain('NO. IMSS 83917114858')
        ->toContain('MIEMBRO ACTIVO DEL')
        ->toContain('SINDICATO ÚNICO DE TRABAJADORES')
        ->toContain('DEL INSTITUTO TECNOLÓGICO')
        ->toContain('SUPERIOR DE MACUSPANA.')
        ->toContain('REG. RS-033/2019.')
        ->toContain('www.siutitsm.com.mx');
});

test('downloadCredential action is hidden on profile page when documents are not valid', function () {
    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    $this->actingAs($user);

    expect($user->areAllDocumentsValid())->toBeFalse();

    Livewire::test(Profile::class)
        ->assertActionHidden('downloadCredential');
});

test('downloadCredential action is visible and downloadable on profile page when all documents are valid', function () {
    Storage::fake('local');

    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
        'curp' => 'TEST010101HDFRRN01',
    ]);

    foreach (UserDocumentType::cases() as $type) {
        $file = UploadedFile::fake()->create("{$type->value}.pdf", 50, 'application/pdf');
        $media = $user->addMedia($file)->toMediaCollection($type->value);
        $media->setCustomProperty('status', DocumentStatus::Valid->value);
        $media->save();
    }

    $this->actingAs($user);

    expect($user->areAllDocumentsValid())->toBeTrue();

    Livewire::test(Profile::class)
        ->assertActionVisible('downloadCredential')
        ->callAction('downloadCredential')
        ->assertFileDownloaded('credencial-TEST010101HDFRRN01.pdf');
});

test('downloadCredential action is visible and downloadable on review documents page for admin', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
        'curp' => 'ADMINREV010101HDFR01',
    ]);

    $this->actingAs($admin);

    Livewire::test(ReviewDocuments::class, ['record' => $user->getRouteKey()])
        ->assertActionExists('downloadCredential')
        ->assertActionVisible('downloadCredential')
        ->callAction('downloadCredential')
        ->assertFileDownloaded('credencial-ADMINREV010101HDFR01.pdf');
});
