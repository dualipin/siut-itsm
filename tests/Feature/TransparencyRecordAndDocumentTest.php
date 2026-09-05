<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Enums\UserRole;
use App\Filament\Resources\TransparencyRecords\Pages\EditTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\RelationManagers\DocumentsRelationManager;
use App\Models\TransparencyDocument;
use App\Models\TransparencyRecord;
use App\Models\User;
use Database\Seeders\TransparencyRecordSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

test('transparency record factory creates a valid record', function () {
    $record = TransparencyRecord::factory()->create();

    expect($record)->toBeInstanceOf(TransparencyRecord::class)
        ->and($record->creator)->toBeInstanceOf(User::class)
        ->and($record->name)->toBeString()
        ->and($record->fiscal_year)->toBeInt()
        ->and($record->period)->toBeString()
        ->and($record->type)->toBeInstanceOf(TransparencyRecordType::class)
        ->and($record->status)->toBeInstanceOf(TransparencyRecordStatus::class);
});

test('transparency record can attach media documents', function () {
    $record = TransparencyRecord::factory()->create();
    $media = $record->addMedia(UploadedFile::fake()->createWithContent('informe.pdf', 'contenido de prueba'))
        ->setName('Informe Financiero')
        ->setFileName('informe.pdf')
        ->toMediaCollection('documents');

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->name)->toBe('Informe Financiero')
        ->and($media->file_name)->toBe('informe.pdf')
        ->and($record->getMedia('documents'))->toHaveCount(1);
});

test('transparency record withDocuments factory state generates related media documents', function () {
    $record = TransparencyRecord::factory()->withDocuments(3)->create();

    expect($record->getMedia('documents'))->toHaveCount(3);
    expect($record->getMedia('documents')->first())->toBeInstanceOf(Media::class);
});

test('transparency record published and draft states work as expected', function () {
    $published = TransparencyRecord::factory()->published()->create();
    $draft = TransparencyRecord::factory()->draft()->create();

    expect($published->status)->toBe(TransparencyRecordStatus::Publicado)
        ->and($published->isPublished())->toBeTrue()
        ->and($draft->status)->toBe(TransparencyRecordStatus::Borrador)
        ->and($draft->isPublished())->toBeFalse();
});

test('scopes filter transparency records correctly', function () {
    TransparencyRecord::query()->forceDelete();

    TransparencyRecord::factory()->published()->create(['fiscal_year' => 2025]);
    TransparencyRecord::factory()->draft()->create(['fiscal_year' => 2025]);
    TransparencyRecord::factory()->published()->create(['fiscal_year' => 2024]);

    expect(TransparencyRecord::published()->count())->toBe(2);
    expect(TransparencyRecord::forFiscalYear(2025)->count())->toBe(2);
    expect(TransparencyRecord::published()->forFiscalYear(2025)->count())->toBe(1);
});

test('user relations to transparency records work', function () {
    $user = User::factory()->create();
    $record = TransparencyRecord::factory()->create(['created_by' => $user->id]);

    expect($user->transparencyRecords->pluck('id'))->toContain($record->id);
});

test('transparency record supports soft deletes', function () {
    $record = TransparencyRecord::factory()->create();
    $record->delete();

    expect(TransparencyRecord::find($record->id))->toBeNull();
    expect(TransparencyRecord::withTrashed()->find($record->id))->not->toBeNull();
});

test('transparency record seeder populates database with records and media', function () {
    $this->seed(TransparencyRecordSeeder::class);

    expect(TransparencyRecord::count())->toBeGreaterThanOrEqual(20);
    expect(Media::count())->toBeGreaterThanOrEqual(20);
});

test('authenticated active user can view portal transparency document inline', function () {
    $user = User::factory()->create(['is_active' => true]);
    $record = TransparencyRecord::factory()->create(['created_by' => $user->id]);
    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Oficio de Presupuesto',
        'published_at' => now(),
        'is_public' => false,
        'uploaded_by' => $user->id,
    ]);

    $document->addMedia(UploadedFile::fake()->createWithContent('presupuesto.pdf', '%PDF-1.4 test'))
        ->setName('Oficio de Presupuesto')
        ->setFileName('presupuesto.pdf')
        ->toMediaCollection('file');

    $response = $this->actingAs($user)->get(route('portal.transparency.documents.show', $document));

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('inline');
});

test('authenticated active user can download portal transparency document with download param', function () {
    $user = User::factory()->create(['is_active' => true]);
    $record = TransparencyRecord::factory()->create(['created_by' => $user->id]);
    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Anexo de Horas',
        'published_at' => now(),
        'is_public' => false,
        'uploaded_by' => $user->id,
    ]);

    $document->addMedia(UploadedFile::fake()->createWithContent('anexo.pdf', '%PDF-1.4 test'))
        ->setName('Anexo de Horas')
        ->setFileName('anexo.pdf')
        ->toMediaCollection('file');

    $response = $this->actingAs($user)->get(route('portal.transparency.documents.show', ['document' => $document, 'download' => 1]));

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toContain('attachment');
});

test('unauthenticated guest is redirected or aborted on portal transparency document route', function () {
    $user = User::factory()->create();
    $record = TransparencyRecord::factory()->create();
    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Documento Protegido',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $user->id,
    ]);

    $document->addMedia(UploadedFile::fake()->createWithContent('protegido.pdf', '%PDF-1.4 test'))
        ->setName('Documento Protegido')
        ->setFileName('protegido.pdf')
        ->toMediaCollection('file');

    $response = $this->get(route('portal.transparency.documents.show', $document));

    $response->assertRedirect('/portal/login');
});

test('inactive user is forbidden from accessing portal transparency document route', function () {
    $inactiveUser = User::factory()->create(['is_active' => false]);
    $record = TransparencyRecord::factory()->create();
    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Documento Confidencial',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $inactiveUser->id,
    ]);

    $document->addMedia(UploadedFile::fake()->createWithContent('confidencial.pdf', '%PDF-1.4 test'))
        ->setName('Documento Confidencial')
        ->setFileName('confidencial.pdf')
        ->toMediaCollection('file');

    $response = $this->actingAs($inactiveUser)->get(route('portal.transparency.documents.show', $document));

    $response->assertForbidden();
});

test('transparency document without media returns 404 on show route', function () {
    $user = User::factory()->create(['is_active' => true]);
    $record = TransparencyRecord::factory()->create(['created_by' => $user->id]);
    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Documento Vacio',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('portal.transparency.documents.show', $document));

    $response->assertNotFound();
});

test('admin can access transparency record edit page and view documents in portal', function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    $admin = User::factory()->create(['is_active' => true, 'role' => UserRole::Admin]);
    $record = TransparencyRecord::factory()->create(['created_by' => $admin->id]);
    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Oficio de Presupuesto 2026',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $admin->id,
    ]);

    $document->addMedia(UploadedFile::fake()->createWithContent('presupuesto.pdf', '%PDF-1.4 test'))
        ->setName('Oficio de Presupuesto 2026')
        ->setFileName('presupuesto.pdf')
        ->toMediaCollection('file');

    $this->actingAs($admin);

    Livewire::test(EditTransparencyRecord::class, [
        'record' => $record->getKey(),
    ])
        ->assertSuccessful()
        ->assertFormSet([
            'name' => $record->name,
            'fiscal_year' => $record->fiscal_year,
            'period' => $record->period,
        ]);
});

test('documents relation manager renders successfully on edit page', function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    $admin = User::factory()->create(['is_active' => true, 'role' => UserRole::Admin]);
    $record = TransparencyRecord::factory()->create(['created_by' => $admin->id]);
    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Reporte Semestral',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $admin->id,
    ]);

    $document->addMedia(UploadedFile::fake()->createWithContent('reporte.pdf', '%PDF-1.4 test'))
        ->setName('Reporte Semestral')
        ->setFileName('reporte.pdf')
        ->toMediaCollection('file');

    $this->actingAs($admin);

    Livewire::test(DocumentsRelationManager::class, [
        'ownerRecord' => $record,
        'pageClass' => EditTransparencyRecord::class,
    ])
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$document]);
});
