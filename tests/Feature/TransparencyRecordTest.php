<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Enums\UserRole;
use App\Filament\Resources\TransparencyRecords\Pages\CreateTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\Pages\EditTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\Pages\ListTransparencyRecords;
use App\Filament\Resources\TransparencyRecords\Pages\ViewTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\TransparencyRecordResource;
use App\Models\TransparencyRecord;
use App\Models\User;
use Database\Seeders\TransparencyRecordSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

beforeEach(function () {
    Storage::fake('public');
});

test('transparency record status enum has only interno and publico cases', function () {
    $cases = TransparencyRecordStatus::cases();

    expect($cases)->toHaveCount(2)
        ->and(TransparencyRecordStatus::Interno->value)->toBe('interno')
        ->and(TransparencyRecordStatus::Interno->getLabel())->toBe('Consulta Interna')
        ->and(TransparencyRecordStatus::Interno->getColor())->toBe('warning')
        ->and(TransparencyRecordStatus::Publico->value)->toBe('publico')
        ->and(TransparencyRecordStatus::Publico->getLabel())->toBe('Público')
        ->and(TransparencyRecordStatus::Publico->getColor())->toBe('success');
});

test('transparency record type enum has 5 consolidated categories with declarative metadata', function () {
    $cases = TransparencyRecordType::cases();

    expect($cases)->toHaveCount(5)
        ->and(array_map(fn (TransparencyRecordType $case) => $case->getSlug(), $cases))
        ->toBe(['financiero', 'oficios', 'actas', 'legal', 'otros']);

    expect(TransparencyRecordType::Financiero->usesPeriod())->toBeTrue()
        ->and(TransparencyRecordType::Financiero->usesDocumentDate())->toBeFalse()
        ->and(TransparencyRecordType::Financiero->usesFolio())->toBeFalse()
        ->and(TransparencyRecordType::Financiero->allowedExtensions())->toContain('pdf', 'jpg', 'xlsx', 'xls', 'csv');

    expect(TransparencyRecordType::Oficios->usesPeriod())->toBeFalse()
        ->and(TransparencyRecordType::Oficios->usesDocumentDate())->toBeTrue()
        ->and(TransparencyRecordType::Oficios->usesFolio())->toBeTrue()
        ->and(TransparencyRecordType::Oficios->allowedExtensions())->not->toContain('xlsx', 'csv');

    expect(TransparencyRecordType::Actas->usesFolio())->toBeTrue()
        ->and(TransparencyRecordType::Legal->usesFolio())->toBeTrue()
        ->and(TransparencyRecordType::Otros->usesFolio())->toBeFalse()
        ->and(TransparencyRecordType::Otros->usesDocumentDate())->toBeTrue();

    expect(TransparencyRecordType::fromSlug('oficios'))->toBe(TransparencyRecordType::Oficios)
        ->and(TransparencyRecordType::fromSlug('invalid'))->toBeNull();
});

test('transparency_documents table is dropped and transparency_records supports document_date, unique nullable reference_number, and nullable period', function () {
    expect(Schema::hasTable('transparency_documents'))->toBeFalse()
        ->and(Schema::hasColumns('transparency_records', ['document_date', 'reference_number', 'period']))->toBeTrue();

    $author = User::factory()->create();

    $first = TransparencyRecord::create([
        'name' => 'Oficio Sin Folio 1',
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-03-15',
        'reference_number' => '',
        'type' => TransparencyRecordType::Oficios,
        'created_by' => $author->id,
    ]);

    $second = TransparencyRecord::create([
        'name' => 'Oficio Sin Folio 2',
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-04-10',
        'reference_number' => null,
        'type' => TransparencyRecordType::Oficios,
        'created_by' => $author->id,
    ]);

    expect($first->status)->toBe(TransparencyRecordStatus::Interno)
        ->and($first->reference_number)->toBeNull()
        ->and($second->reference_number)->toBeNull();

    TransparencyRecord::create([
        'name' => 'Oficio Con Folio',
        'fiscal_year' => 2025,
        'document_date' => '2025-05-01',
        'reference_number' => 'SIUT/001/2025',
        'type' => TransparencyRecordType::Oficios,
        'created_by' => $author->id,
    ]);

    expect(fn () => TransparencyRecord::create([
        'name' => 'Oficio Folio Duplicado',
        'fiscal_year' => 2025,
        'document_date' => '2025-05-02',
        'reference_number' => 'SIUT/001/2025',
        'type' => TransparencyRecordType::Oficios,
        'created_by' => $author->id,
    ]))->toThrow(QueryException::class);
});

test('transparency record attaches multiple documents directly via medialibrary and scopes filter by status', function () {
    TransparencyRecord::query()->forceDelete();

    $publicRecord = TransparencyRecord::factory()->publico()->create(['fiscal_year' => 2025]);
    $internoRecord = TransparencyRecord::factory()->interno()->create(['fiscal_year' => 2025]);

    $publicRecord->addMedia(UploadedFile::fake()->createWithContent('uno.pdf', '%PDF-1.4 1'))
        ->toMediaCollection('documents');
    $publicRecord->addMedia(UploadedFile::fake()->createWithContent('dos.pdf', '%PDF-1.4 2'))
        ->toMediaCollection('documents');

    expect($publicRecord->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION))->toHaveCount(2)
        ->and($publicRecord->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION)->first())->toBeInstanceOf(Media::class)
        ->and($publicRecord->isPublico())->toBeTrue()
        ->and($internoRecord->isPublico())->toBeFalse()
        ->and($internoRecord->isInterno())->toBeTrue()
        ->and(TransparencyRecord::publico()->count())->toBe(1)
        ->and(TransparencyRecord::interno()->count())->toBe(1);
});

test('transparency record seeder populates database with records and media', function () {
    $this->seed(TransparencyRecordSeeder::class);

    expect(TransparencyRecord::count())->toBeGreaterThanOrEqual(10)
        ->and(Media::count())->toBeGreaterThanOrEqual(10);
});

test('transparency record resource has no relation managers and renders all pages for directiva', function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    expect(TransparencyRecordResource::getRelations())->toBeEmpty();

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $record = TransparencyRecord::factory()->withDocuments(1)->create([
        'type' => TransparencyRecordType::Oficios,
        'period' => null,
        'document_date' => '2025-05-10',
        'reference_number' => 'SIUT/010/2025',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListTransparencyRecords::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$record]);

    Livewire::test(ViewTransparencyRecord::class, ['record' => $record->getKey()])
        ->assertSuccessful();

    Livewire::test(EditTransparencyRecord::class, ['record' => $record->getKey()])
        ->assertSuccessful()
        ->assertFormSet([
            'name' => $record->name,
            'reference_number' => 'SIUT/010/2025',
        ])
        ->fillForm([
            'name' => 'Oficio Actualizado 2025',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($record->fresh()->name)->toBe('Oficio Actualizado 2025');

    Livewire::test(CreateTransparencyRecord::class)
        ->assertSuccessful()
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'name' => 'Balance Anual 2025',
            'fiscal_year' => 2025,
            'period' => 'Anual',
            'status' => TransparencyRecordStatus::Interno->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'name' => 'Balance Anual 2025',
        'type' => TransparencyRecordType::Financiero->value,
        'status' => TransparencyRecordStatus::Interno->value,
    ]);
});

test('agremiado cannot access transparency record filament resource pages', function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $record = TransparencyRecord::factory()->create();

    $this->actingAs($agremiado);

    $this->get(TransparencyRecordResource::getUrl('index'))->assertForbidden();
    $this->get(TransparencyRecordResource::getUrl('create'))->assertForbidden();
    $this->get(TransparencyRecordResource::getUrl('view', ['record' => $record]))->assertForbidden();
    $this->get(TransparencyRecordResource::getUrl('edit', ['record' => $record]))->assertForbidden();
});

test('ownsDocumentMedia verifies record ownership and documents collection', function () {
    $record = TransparencyRecord::factory()->create();
    $otherRecord = TransparencyRecord::factory()->create();

    $ownedMedia = $record->addMedia(UploadedFile::fake()->createWithContent('owned.pdf', '%PDF-1.4'))
        ->toMediaCollection(TransparencyRecord::DOCUMENTS_COLLECTION);
    $otherCollectionMedia = $record->addMedia(UploadedFile::fake()->createWithContent('other-col.pdf', '%PDF-1.4'))
        ->toMediaCollection('other_collection');
    $foreignMedia = $otherRecord->addMedia(UploadedFile::fake()->createWithContent('foreign.pdf', '%PDF-1.4'))
        ->toMediaCollection(TransparencyRecord::DOCUMENTS_COLLECTION);

    expect($record->ownsDocumentMedia($ownedMedia))->toBeTrue()
        ->and($record->ownsDocumentMedia($otherCollectionMedia))->toBeFalse()
        ->and($record->ownsDocumentMedia($foreignMedia))->toBeFalse();
});

test('migration rollback backfills null period to anual when non-financiero records exist', function () {
    $author = User::factory()->create();

    $nonFinancial = TransparencyRecord::factory()->create([
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-05-01',
        'created_by' => $author->id,
    ]);

    try {
        Artisan::call('migrate:rollback', ['--step' => 1]);

        expect(Schema::hasTable('transparency_documents'))->toBeTrue()
            ->and(Schema::hasColumns('transparency_records', ['reference_number', 'document_date']))->toBeFalse()
            ->and(DB::table('transparency_records')->where('id', $nonFinancial->id)->value('period'))->toBe('Anual');
    } finally {
        Artisan::call('migrate');
    }

    expect(Schema::hasTable('transparency_documents'))->toBeFalse()
        ->and(Schema::hasColumns('transparency_records', ['reference_number', 'document_date']))->toBeTrue();
});
