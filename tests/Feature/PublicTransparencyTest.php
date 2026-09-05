<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyDocument;
use App\Models\TransparencyRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createFakeTransparencyPdf(string $name = 'document.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
    );
}

beforeEach(function () {
    Storage::fake('public');
});

test('guest can visit the public transparency type page and see records', function () {
    $author = User::factory()->create();

    $publishedRecord = TransparencyRecord::factory()->create([
        'name' => 'Reporte Financiero Publico 2025',
        'type' => TransparencyRecordType::FINANCIERO,
        'status' => TransparencyRecordStatus::Publicado,
        'fiscal_year' => 2025,
        'period' => '1er Trimestre',
        'created_by' => $author->id,
    ]);

    $draftRecord = TransparencyRecord::factory()->create([
        'name' => 'Borrador Financiero Oculto 2025',
        'type' => TransparencyRecordType::FINANCIERO,
        'status' => TransparencyRecordStatus::Borrador,
        'fiscal_year' => 2025,
        'period' => '2do Trimestre',
        'created_by' => $author->id,
    ]);

    $response = $this->get(route('transparency.type', ['type' => 'financiero']));

    $response->assertSuccessful();
    $response->assertSee('Reporte Financiero Publico 2025');
    // It should also show the draft record because the instruction says:
    // "se van a mostrar todas pero no se podran acceder a las que no sean publicas"
    $response->assertSee('Borrador Financiero Oculto 2025');
    $response->assertSee('2025');
    $response->assertSee('1er Trimestre');
    $response->assertSee('2do Trimestre');
});

test('guest receives 404 for invalid transparency type', function () {
    $response = $this->get(route('transparency.type', ['type' => 'invalid-type']));

    $response->assertStatus(404);
});

test('guest can download public document of public record', function () {
    $author = User::factory()->create();

    $record = TransparencyRecord::factory()->create([
        'type' => TransparencyRecordType::FINANCIERO,
        'status' => TransparencyRecordStatus::Publicado,
        'fiscal_year' => 2025,
        'period' => '1er Trimestre',
        'created_by' => $author->id,
    ]);

    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Documento Publico',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $author->id,
    ]);

    $document->addMedia(createFakeTransparencyPdf('documento_publico.pdf'))
        ->setName('Documento Publico')
        ->toMediaCollection('file');

    $response = $this->get(route('transparency.documents.download', $document));

    $response->assertSuccessful();
    $response->assertHeader('content-disposition', 'attachment; filename=documento_publico.pdf');
});

test('guest cannot download private document of public record', function () {
    $author = User::factory()->create();

    $record = TransparencyRecord::factory()->create([
        'type' => TransparencyRecordType::FINANCIERO,
        'status' => TransparencyRecordStatus::Publicado,
        'fiscal_year' => 2025,
        'period' => '1er Trimestre',
        'created_by' => $author->id,
    ]);

    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Documento Privado',
        'published_at' => now(),
        'is_public' => false,
        'uploaded_by' => $author->id,
    ]);

    $document->addMedia(createFakeTransparencyPdf('documento_privado.pdf'))
        ->setName('Documento Privado')
        ->toMediaCollection('file');

    $response = $this->get(route('transparency.documents.download', $document));

    $response->assertStatus(403);
});

test('guest cannot download document of non-published record', function () {
    $author = User::factory()->create();

    $record = TransparencyRecord::factory()->create([
        'type' => TransparencyRecordType::FINANCIERO,
        'status' => TransparencyRecordStatus::Borrador,
        'fiscal_year' => 2025,
        'period' => '1er Trimestre',
        'created_by' => $author->id,
    ]);

    $document = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Documento en Borrador',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $author->id,
    ]);

    $document->addMedia(createFakeTransparencyPdf('documento_borrador.pdf'))
        ->setName('Documento en Borrador')
        ->toMediaCollection('file');

    $response = $this->get(route('transparency.documents.download', $document));

    $response->assertStatus(403);
});

test('public page does not render download link for private documents or non-public records', function () {
    $author = User::factory()->create();

    // 1. Published record with one public and one private document
    $publicRecord = TransparencyRecord::factory()->create([
        'name' => 'Record Publico',
        'type' => TransparencyRecordType::FINANCIERO,
        'status' => TransparencyRecordStatus::Publicado,
        'fiscal_year' => 2025,
        'period' => '1er Trimestre',
        'created_by' => $author->id,
    ]);

    $publicDoc = TransparencyDocument::create([
        'transparency_record_id' => $publicRecord->id,
        'name' => 'Doc Publico',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $author->id,
    ]);
    $publicDoc->addMedia(createFakeTransparencyPdf('doc_publico.pdf'))
        ->setName('Doc Publico')
        ->toMediaCollection('file');

    $privateDoc = TransparencyDocument::create([
        'transparency_record_id' => $publicRecord->id,
        'name' => 'Doc Privado',
        'published_at' => now(),
        'is_public' => false,
        'uploaded_by' => $author->id,
    ]);
    $privateDoc->addMedia(createFakeTransparencyPdf('doc_privado.pdf'))
        ->setName('Doc Privado')
        ->toMediaCollection('file');

    // 2. Draft record with a public document
    $draftRecord = TransparencyRecord::factory()->create([
        'name' => 'Record Borrador',
        'type' => TransparencyRecordType::FINANCIERO,
        'status' => TransparencyRecordStatus::Borrador,
        'fiscal_year' => 2025,
        'period' => '1er Trimestre',
        'created_by' => $author->id,
    ]);

    $draftDoc = TransparencyDocument::create([
        'transparency_record_id' => $draftRecord->id,
        'name' => 'Doc Borrador',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $author->id,
    ]);
    $draftDoc->addMedia(createFakeTransparencyPdf('doc_borrador.pdf'))
        ->setName('Doc Borrador')
        ->toMediaCollection('file');

    $response = $this->get(route('transparency.type', ['type' => 'financiero']));

    $response->assertSuccessful();

    // Check that we see all records
    $response->assertSee('Record Publico');
    $response->assertSee('Record Borrador');

    // Check that the download route for the public document IS visible
    $response->assertSee(route('transparency.documents.download', $publicDoc));

    // Check that the download route for the private document IS NOT visible
    $response->assertDontSee(route('transparency.documents.download', $privateDoc));

    // Check that the download route for the draft record document IS NOT visible
    $response->assertDontSee(route('transparency.documents.download', $draftDoc));

    // Check that lock indicators are rendered
    $response->assertSee('Restringido');
    $response->assertSee('No disponible para descarga');
});

test('legacy repositorios urls redirect to correct public pages', function () {
    $this->get('/sindicato/repositorios/gestoria')
        ->assertRedirect('/transparencia/gestoria');

    $this->get('/sindicato/repositorios/gremiales')
        ->assertRedirect('/transparencia/gremiales');

    $this->get('/sindicato/repositorios/tramites')
        ->assertRedirect('/transparencia/tramites');

    $this->get('/sindicato/repositorios/minutas')
        ->assertRedirect('/transparencia/minutas');
});

test('legacy transparency type urls redirect to new routes', function () {
    $this->get('/transparencia/acta')
        ->assertRedirect('/transparencia/minutas');

    $this->get('/transparencia/convenio')
        ->assertRedirect('/transparencia/legal');

    $this->get('/transparencia/normativo')
        ->assertRedirect('/transparencia/normativos');
});

test('guest can visit all new transparency types in both lower and upper case', function (TransparencyRecordType $type) {
    $responseLower = $this->get('/transparencia/'.strtolower($type->value));
    $responseLower->assertSuccessful();
    $responseLower->assertSee($type->getLabel());

    $responseUpper = $this->get('/transparencia/'.$type->value);
    $responseUpper->assertSuccessful();
    $responseUpper->assertSee($type->getLabel());
})->with(TransparencyRecordType::cases());
