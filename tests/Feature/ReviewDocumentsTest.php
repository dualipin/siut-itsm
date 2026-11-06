<?php

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\ReviewDocuments;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($this->admin);
});

test('admin can access review documents page', function () {
    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    $this->get(route('filament.portal.resources.users.review-documents', ['record' => $user]))
        ->assertSuccessful()
        ->assertSee('Revisión de Documentos')
        ->assertSee('Expediente de Afiliación')
        ->assertSee('Datos del Perfil');
});

test('review documents page mounts with active document type', function () {
    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    Livewire::test(ReviewDocuments::class, ['record' => $user->getRouteKey()])
        ->assertSet('activeDocumentType', 'afiliacion')
        ->assertActionExists('approveDocument')
        ->assertActionExists('rejectDocument')
        ->assertActionExists('replaceDocument')
        ->assertActionExists('setDocumentValues');
});

test('admin can approve an uploaded document', function () {
    Storage::fake('local');

    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    $file = UploadedFile::fake()->create('afiliacion.pdf', 100, 'application/pdf');
    $user->addMedia($file)->toMediaCollection(UserDocumentType::Afiliacion->value);

    expect($user->getDocumentStatus(UserDocumentType::Afiliacion))->toBe(DocumentStatus::Pending);

    Livewire::test(ReviewDocuments::class, ['record' => $user->getRouteKey()])
        ->callAction('approveDocument', arguments: ['type' => UserDocumentType::Afiliacion->value]);

    $user->refresh();
    expect($user->getDocumentStatus(UserDocumentType::Afiliacion))->toBe(DocumentStatus::Valid);
});

test('admin can reject an uploaded document with reason', function () {
    Storage::fake('local');

    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    $file = UploadedFile::fake()->create('curp.pdf', 100, 'application/pdf');
    $user->addMedia($file)->toMediaCollection(UserDocumentType::Curp->value);

    Livewire::test(ReviewDocuments::class, ['record' => $user->getRouteKey()])
        ->callAction('rejectDocument', data: [
            'rejection_reason' => 'El CURP es ilegible en la esquina inferior.',
        ], arguments: ['type' => UserDocumentType::Curp->value]);

    $user->refresh();
    expect($user->getDocumentStatus(UserDocumentType::Curp))->toBe(DocumentStatus::Invalid);
    expect($user->getDocumentRejectionReason(UserDocumentType::Curp))->toBe('El CURP es ilegible en la esquina inferior.');
});

test('replacing a document refreshes the preview url and file name', function () {
    Storage::fake('local');

    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    $user->addMedia(UploadedFile::fake()->create('viejo.pdf', 100, 'application/pdf'))
        ->toMediaCollection(UserDocumentType::Curp->value);
    $oldMediaId = $user->fresh()->getDocumentMedia(UserDocumentType::Curp)->getKey();

    Livewire::test(ReviewDocuments::class, ['record' => $user->getRouteKey()])
        ->assertSeeHtml('v='.$oldMediaId)
        ->callAction(
            'replaceDocument',
            data: ['document_file' => UploadedFile::fake()->create('nuevo.pdf', 100, 'application/pdf')],
            arguments: ['type' => UserDocumentType::Curp->value],
        )
        ->assertHasNoActionErrors()
        ->assertDontSeeHtml('v='.$oldMediaId.'"');

    $newMedia = $user->fresh()->getDocumentMedia(UserDocumentType::Curp);

    expect($newMedia->getKey())->not->toBe($oldMediaId);
});

test('admin can approve all documents at once', function () {
    Storage::fake('local');

    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    foreach (UserDocumentType::cases() as $type) {
        $file = UploadedFile::fake()->create("{$type->value}.pdf", 100, 'application/pdf');
        $user->addMedia($file)->toMediaCollection($type->value);
    }

    expect($user->areAllDocumentsValid())->toBeFalse();

    Livewire::test(ReviewDocuments::class, ['record' => $user->getRouteKey()])
        ->callAction('approveAllDocuments');

    $user->refresh();
    expect($user->areAllDocumentsValid())->toBeTrue();
});

test('review documents page orders navigation by name and handles null surnames', function () {
    $firstUser = User::factory()->create([
        'name' => '111 Primero',
        'surnames' => null,
        'role' => UserRole::Admin,
    ]);

    $secondUser = User::factory()->create([
        'name' => '222 Segundo',
        'surnames' => 'Perez',
        'role' => UserRole::Agremiado,
    ]);

    $thirdUser = User::factory()->create([
        'name' => '333 Tercero',
        'surnames' => 'Alvarez',
        'role' => UserRole::Agremiado,
    ]);

    $this->get(route('filament.portal.resources.users.review-documents', ['record' => $firstUser]))
        ->assertSuccessful()
        ->assertSee('Revisión de Documentos');

    // Ordered by name: 111 Primero -> 222 Segundo (Perez) -> 333 Tercero (Alvarez)
    // If it were ordered by surname, Alvarez (333) would come before Perez (222).
    Livewire::test(ReviewDocuments::class, ['record' => $secondUser->getRouteKey()])
        ->assertActionEnabled('previousRecord')
        ->assertActionEnabled('nextRecord')
        ->assertSeeHtml(UserResource::getUrl('review-documents', ['record' => $firstUser]))
        ->assertSeeHtml(UserResource::getUrl('review-documents', ['record' => $thirdUser]));
});
