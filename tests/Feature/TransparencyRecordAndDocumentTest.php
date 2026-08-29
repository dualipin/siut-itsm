<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use App\Models\User;
use Database\Seeders\TransparencyRecordSeeder;
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
    $media = $record->addMediaFromString('contenido de prueba')
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
