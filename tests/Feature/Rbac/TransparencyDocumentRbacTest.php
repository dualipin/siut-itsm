<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\UserRole;
use App\Models\TransparencyDocument;
use App\Models\TransparencyRecord;
use App\Models\User;
use App\Policies\TransparencyDocumentPolicy;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('public');
});

test('agremiado can open documents of published records but is forbidden on unpublished records', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $leader = User::factory()->lider()->create(['is_active' => true]);

    $publishedRecord = TransparencyRecord::factory()->create([
        'status' => TransparencyRecordStatus::Publicado,
        'created_by' => $admin->id,
    ]);

    $draftRecord = TransparencyRecord::factory()->create([
        'status' => TransparencyRecordStatus::Borrador,
        'created_by' => $admin->id,
    ]);

    $publishedDoc = TransparencyDocument::create([
        'transparency_record_id' => $publishedRecord->id,
        'name' => 'Presupuesto Publicado',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $admin->id,
    ]);
    $publishedDoc->addMedia(UploadedFile::fake()->createWithContent('pub.pdf', '%PDF-1.4 pub'))
        ->setFileName('pub.pdf')
        ->toMediaCollection('file');

    $draftDoc = TransparencyDocument::create([
        'transparency_record_id' => $draftRecord->id,
        'name' => 'Borrador Confidencial',
        'published_at' => null,
        'is_public' => false,
        'uploaded_by' => $admin->id,
    ]);
    $draftDoc->addMedia(UploadedFile::fake()->createWithContent('draft.pdf', '%PDF-1.4 draft'))
        ->setFileName('draft.pdf')
        ->toMediaCollection('file');

    $policy = new TransparencyDocumentPolicy;

    // Policy checks
    expect($policy->view($agremiado, $publishedDoc))->toBeTrue();
    expect($policy->view($agremiado, $draftDoc))->toBeFalse();
    expect($policy->view($admin, $draftDoc))->toBeTrue();
    expect($policy->view($leader, $draftDoc))->toBeTrue();

    // HTTP checks for Agremiado
    $this->actingAs($agremiado);
    $this->get(route('portal.transparency.documents.show', $publishedDoc))->assertSuccessful();
    $this->get(route('portal.transparency.documents.show', $draftDoc))->assertForbidden();

    // HTTP checks for Admin and Leader
    $this->actingAs($admin);
    $this->get(route('portal.transparency.documents.show', $draftDoc))->assertSuccessful();

    $this->actingAs($leader);
    $this->get(route('portal.transparency.documents.show', $draftDoc))->assertSuccessful();
});
