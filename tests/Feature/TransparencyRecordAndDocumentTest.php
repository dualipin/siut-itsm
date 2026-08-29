<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\Document;
use App\Models\TransparencyRecord;
use App\Models\User;
use Database\Seeders\DocumentSeeder;
use Database\Seeders\TransparencyRecordSeeder;

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

test('document factory creates a valid document with relationships', function () {
    $document = Document::factory()->create();

    expect($document)->toBeInstanceOf(Document::class)
        ->and($document->record)->toBeInstanceOf(TransparencyRecord::class)
        ->and($document->transparencyRecord)->toBeInstanceOf(TransparencyRecord::class)
        ->and($document->owner)->toBeInstanceOf(User::class)
        ->and($document->uploader)->toBeInstanceOf(User::class)
        ->and($document->display_name)->toBeString()
        ->and($document->file_path)->toBeString()
        ->and($document->file_name)->toBeString()
        ->and($document->mime_type)->toBeString()
        ->and($document->is_public)->toBeTrue();
});

test('transparency record withDocuments factory state generates related documents', function () {
    $record = TransparencyRecord::factory()->withDocuments(3)->create();

    expect($record->documents)->toHaveCount(3);
    expect($record->documents->first())->toBeInstanceOf(Document::class);
});

test('transparency record published and draft states work as expected', function () {
    $published = TransparencyRecord::factory()->published()->create();
    $draft = TransparencyRecord::factory()->draft()->create();

    expect($published->status)->toBe(TransparencyRecordStatus::Publicado);
    expect($published->isPublished())->toBeTrue();

    expect($draft->status)->toBe(TransparencyRecordStatus::Borrador);
    expect($draft->isPublished())->toBeFalse();
});

test('scopes filter records and documents correctly', function () {
    TransparencyRecord::query()->forceDelete();
    Document::query()->forceDelete();

    TransparencyRecord::factory()->published()->create(['fiscal_year' => 2025]);
    TransparencyRecord::factory()->draft()->create(['fiscal_year' => 2025]);
    TransparencyRecord::factory()->published()->create(['fiscal_year' => 2024]);

    expect(TransparencyRecord::published()->count())->toBe(2);
    expect(TransparencyRecord::forFiscalYear(2025)->count())->toBe(2);
    expect(TransparencyRecord::published()->forFiscalYear(2025)->count())->toBe(1);

    Document::factory()->public()->create();
    Document::factory()->private()->create();

    expect(Document::public()->count())->toBe(1);
});

test('document formatted file size accessor formats bytes nicely', function () {
    $docSmall = Document::factory()->create(['file_size' => 1024 * 500]); // 500 KB
    $docMb = Document::factory()->create(['file_size' => 1024 * 1024 * 5]); // 5 MB

    expect($docSmall->formatted_file_size)->toContain('KB');
    expect($docMb->formatted_file_size)->toContain('MB');
});

test('user relations to transparency records and documents work', function () {
    $user = User::factory()->create();

    $record = TransparencyRecord::factory()->create(['created_by' => $user->id]);
    $docUploaded = Document::factory()->create([
        'record_id' => $record->id,
        'uploaded_by' => $user->id,
    ]);
    $docOwned = Document::factory()->create([
        'record_id' => $record->id,
        'owner_id' => $user->id,
    ]);

    expect($user->transparencyRecords->pluck('id'))->toContain($record->id);
    expect($user->documents->pluck('id'))->toContain($docUploaded->id);
    expect($user->ownedDocuments->pluck('id'))->toContain($docOwned->id);
});

test('transparency record and document support soft deletes', function () {
    $record = TransparencyRecord::factory()->create();
    $doc = Document::factory()->create(['record_id' => $record->id]);

    $doc->delete();
    $record->delete();

    expect(TransparencyRecord::find($record->id))->toBeNull();
    expect(TransparencyRecord::withTrashed()->find($record->id))->not->toBeNull();

    expect(Document::find($doc->id))->toBeNull();
    expect(Document::withTrashed()->find($doc->id))->not->toBeNull();
});

test('transparency record seeder populates database', function () {
    $this->seed(TransparencyRecordSeeder::class);

    expect(TransparencyRecord::count())->toBeGreaterThanOrEqual(20);
    expect(Document::count())->toBeGreaterThanOrEqual(20);
});

test('document seeder populates documents for existing records', function () {
    $record = TransparencyRecord::factory()->create();
    $this->seed(DocumentSeeder::class);

    expect($record->documents()->count())->toBeGreaterThanOrEqual(2);
});
