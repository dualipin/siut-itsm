<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
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

test('landing config repositorios menu links to the 5 consolidated transparency categories', function () {
    $sindicato = collect(config('landing.links'))->firstWhere('label', 'Sindicato');
    $repositorios = collect($sindicato['items'] ?? [])->firstWhere('label', 'Repositorios');
    $hrefs = collect($repositorios['items'] ?? [])->pluck('href')->all();

    expect($hrefs)->toBe([
        '/transparencia/financiero',
        '/transparencia/oficios',
        '/transparencia/actas',
        '/transparencia/legal',
        '/transparencia/otros',
    ]);
});

test('guest can visit all 5 transparency categories and normativos portal', function (TransparencyRecordType $type) {
    $response = $this->get('/transparencia/'.$type->getSlug());

    $response->assertSuccessful();
    $response->assertSee($type->getLabel());
})->with(TransparencyRecordType::cases());

test('normativos page remains intact', function () {
    $this->get('/transparencia/normativos')->assertSuccessful();
});

test('retired and invalid transparency routes return 404', function (string $uri) {
    $this->get($uri)->assertNotFound();
})->with([
    '/transparencia/administrativo',
    '/transparencia/sindical',
    '/transparencia/gestoria',
    '/transparencia/gremiales',
    '/transparencia/tramites',
    '/transparencia/minutas',
    '/transparencia/otro',
    '/transparencia/acta',
    '/transparencia/convenio',
    '/transparencia/normativo',
    '/sindicato/repositorios/gestoria',
    '/sindicato/repositorios/minutas',
    '/transparencia/invalid-type',
]);

test('public transparency page lists both publico and interno records and restricts download links to publico', function () {
    $author = User::factory()->create();

    $publicRecord = TransparencyRecord::factory()->publico()->create([
        'name' => 'Estado Financiero Q1 2025',
        'type' => TransparencyRecordType::Financiero,
        'fiscal_year' => 2025,
        'period' => '1er Trimestre',
        'created_by' => $author->id,
    ]);
    $publicMedia = $publicRecord->addMedia(createFakeTransparencyPdf('SCAN_001.pdf'))
        ->toMediaCollection('documents');

    $internoRecord = TransparencyRecord::factory()->interno()->create([
        'name' => 'Expediente Reservado Q2 2025',
        'type' => TransparencyRecordType::Financiero,
        'fiscal_year' => 2025,
        'period' => '2do Trimestre',
        'created_by' => $author->id,
    ]);
    $internoMedia = $internoRecord->addMedia(createFakeTransparencyPdf('INTERNO_002.pdf'))
        ->toMediaCollection('documents');

    $response = $this->get(route('transparency.type', ['type' => 'financiero']));

    $response->assertSuccessful();
    $response->assertSee('Estado Financiero Q1 2025');
    $response->assertSee('Expediente Reservado Q2 2025');
    $response->assertSee('Consulta Interna');
    $response->assertSee(route('transparency.documents.download', [$publicRecord, $publicMedia]));
    $response->assertDontSee(route('transparency.documents.download', [$internoRecord, $internoMedia]));
    $response->assertSee('No disponible para descarga');
});

test('public download uses clean record slug filename when publico record has a single file', function () {
    $author = User::factory()->create();

    $record = TransparencyRecord::factory()->publico()->create([
        'name' => 'Oficio de Comisión Sindical 31',
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-04-12',
        'created_by' => $author->id,
    ]);
    $media = $record->addMedia(createFakeTransparencyPdf('SCAN_20250412_0099.pdf'))
        ->toMediaCollection('documents');

    $response = $this->get(route('transparency.documents.download', [$record, $media]));

    $response->assertSuccessful();
    $response->assertHeader('content-disposition', 'attachment; filename=oficio-de-comision-sindical-31.pdf');
});

test('public download uses stored filename when publico record has multiple files', function () {
    $author = User::factory()->create();

    $record = TransparencyRecord::factory()->publico()->create([
        'name' => 'Paquete de Actas 2025',
        'type' => TransparencyRecordType::Actas,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-05-01',
        'created_by' => $author->id,
    ]);
    $firstMedia = $record->addMedia(createFakeTransparencyPdf('acta-mayo.pdf'))
        ->toMediaCollection('documents');
    $record->addMedia(createFakeTransparencyPdf('anexo-firmas.pdf'))
        ->toMediaCollection('documents');

    $response = $this->get(route('transparency.documents.download', [$record, $firstMedia]));

    $response->assertSuccessful();
    $response->assertHeader('content-disposition', 'attachment; filename=acta-mayo.pdf');
});

test('public download returns 403 for interno records and mismatched media', function () {
    $author = User::factory()->create();

    $internoRecord = TransparencyRecord::factory()->interno()->create([
        'type' => TransparencyRecordType::Oficios,
        'status' => TransparencyRecordStatus::Interno,
        'created_by' => $author->id,
    ]);
    $internoMedia = $internoRecord->addMedia(createFakeTransparencyPdf('secreto.pdf'))
        ->toMediaCollection('documents');

    $publicRecord = TransparencyRecord::factory()->publico()->create([
        'type' => TransparencyRecordType::Oficios,
        'created_by' => $author->id,
    ]);

    $this->get(route('transparency.documents.download', [$internoRecord, $internoMedia]))
        ->assertForbidden();

    $this->get(route('transparency.documents.download', [$publicRecord, $internoMedia]))
        ->assertForbidden();
});
