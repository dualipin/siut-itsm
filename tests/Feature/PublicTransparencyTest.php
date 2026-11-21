<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        'observations' => null,
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
    $response->assertDontSee('SCAN_001.pdf');
    $response->assertDontSee(route('transparency.documents.download', [$internoRecord, $internoMedia]));
    $response->assertDontSee('INTERNO_002.pdf');
    $response->assertSee('Solo consulta interna');
    $response->assertSee('Motivo de acceso interno');
    $response->assertSee('Disponible únicamente para consulta interna.');
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

test('public transparency page eager loads media without n plus one queries', function () {
    $author = User::factory()->create();

    foreach (range(1, 5) as $index) {
        $record = TransparencyRecord::factory()->publico()->create([
            'name' => "Oficio Público {$index}",
            'type' => TransparencyRecordType::Oficios,
            'fiscal_year' => 2025,
            'period' => null,
            'document_date' => sprintf('2025-05-%02d', $index),
            'created_by' => $author->id,
        ]);
        $record->addMedia(createFakeTransparencyPdf("doc-{$index}.pdf"))
            ->toMediaCollection('documents');
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->get(route('transparency.type', ['type' => 'oficios']));

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $response->assertSuccessful();

    $transparencyQueries = collect($queries)->filter(
        fn (array $query): bool => str_contains($query['query'], 'transparency_records') || str_contains($query['query'], 'media')
    );

    expect($transparencyQueries)->toHaveCount(2);
});

test('financiero groups by descending fiscal year and logical closed-list period order', function () {
    $author = User::factory()->create();

    $records = [
        ['name' => 'Reporte Diciembre 2025', 'fiscal_year' => 2025, 'period' => 'Diciembre'],
        ['name' => 'Reporte Enero 2025', 'fiscal_year' => 2025, 'period' => 'Enero'],
        ['name' => 'Reporte 2do Semestre 2025', 'fiscal_year' => 2025, 'period' => '2do Semestre'],
        ['name' => 'Reporte 1er Trimestre 2025', 'fiscal_year' => 2025, 'period' => '1er Trimestre'],
        ['name' => 'Reporte Anual 2025', 'fiscal_year' => 2025, 'period' => 'Anual'],
        ['name' => 'Reporte Anual 2024', 'fiscal_year' => 2024, 'period' => 'Anual'],
        ['name' => 'Reporte 1er Trimestre 2026', 'fiscal_year' => 2026, 'period' => '1er Trimestre'],
    ];

    foreach ($records as $item) {
        TransparencyRecord::factory()->interno()->create([
            'name' => $item['name'],
            'type' => TransparencyRecordType::Financiero,
            'fiscal_year' => $item['fiscal_year'],
            'period' => $item['period'],
            'document_date' => null,
            'created_by' => $author->id,
        ]);
    }

    $response = $this->get(route('transparency.type', ['type' => 'financiero']));

    $response->assertSuccessful();
    $response->assertSeeInOrder([
        '2026',
        'Reporte 1er Trimestre 2026',
        '2025',
        'Anual',
        'Reporte Anual 2025',
        '1er Trimestre',
        'Reporte 1er Trimestre 2025',
        '2do Semestre',
        'Reporte 2do Semestre 2025',
        'Enero',
        'Reporte Enero 2025',
        'Diciembre',
        'Reporte Diciembre 2025',
        '2024',
        'Reporte Anual 2024',
    ]);
});

test('non-financiero categories group by descending fiscal year, descending spanish month, and descending document date', function (TransparencyRecordType $type) {
    $author = User::factory()->create();

    TransparencyRecord::factory()->interno()->create([
        'name' => 'Registro Enero 2025',
        'type' => $type,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-01-10',
        'created_by' => $author->id,
    ]);

    TransparencyRecord::factory()->interno()->create([
        'name' => 'Registro Diciembre Temprano 2025',
        'type' => $type,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-12-05',
        'created_by' => $author->id,
    ]);

    TransparencyRecord::factory()->interno()->create([
        'name' => 'Registro Diciembre Tardio 2025',
        'type' => $type,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-12-20',
        'created_by' => $author->id,
    ]);

    TransparencyRecord::factory()->interno()->create([
        'name' => 'Registro Abril 2025',
        'type' => $type,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-04-15',
        'created_by' => $author->id,
    ]);

    TransparencyRecord::factory()->interno()->create([
        'name' => 'Registro Sin Fecha 2025',
        'type' => $type,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => null,
        'created_by' => $author->id,
    ]);

    TransparencyRecord::factory()->interno()->create([
        'name' => 'Registro Junio 2024',
        'type' => $type,
        'fiscal_year' => 2024,
        'period' => null,
        'document_date' => '2024-06-01',
        'created_by' => $author->id,
    ]);

    $response = $this->get(route('transparency.type', ['type' => $type->getSlug()]));

    $response->assertSuccessful();
    $response->assertSeeInOrder([
        '2025',
        'Diciembre',
        'Registro Diciembre Tardio 2025',
        'Registro Diciembre Temprano 2025',
        'Abril',
        'Registro Abril 2025',
        'Enero',
        'Registro Enero 2025',
        'Sin fecha',
        'Registro Sin Fecha 2025',
        '2024',
        'Junio',
        'Registro Junio 2024',
    ]);
})->with([
    TransparencyRecordType::Oficios,
    TransparencyRecordType::Actas,
    TransparencyRecordType::Legal,
    TransparencyRecordType::Otros,
]);

test('cards render folio, formatted document date, summary, visibility and hide scanner filenames or internal links', function () {
    $author = User::factory()->create();

    $publicSingle = TransparencyRecord::factory()->publico()->create([
        'name' => 'Oficio de Comisión Sindical',
        'reference_number' => 'ITSM-SIUTITSM/031/04/2023',
        'summary' => 'Comisión ante dirección académica.',
        'observations' => 'Nota legacy oculta en público',
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2023,
        'period' => null,
        'document_date' => '2023-04-27',
        'created_by' => $author->id,
    ]);
    $singleMedia = $publicSingle->addMedia(createFakeTransparencyPdf('ESCANER_RAW_0042.pdf'))
        ->toMediaCollection('documents');

    $publicMulti = TransparencyRecord::factory()->publico()->create([
        'name' => 'Expediente Completo de Gestión',
        'reference_number' => null,
        'summary' => null,
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2023,
        'period' => null,
        'document_date' => '2023-04-10',
        'created_by' => $author->id,
    ]);
    $multiFirst = $publicMulti->addMedia(createFakeTransparencyPdf('acta-asamblea.pdf'))
        ->toMediaCollection('documents');
    $multiSecond = $publicMulti->addMedia(createFakeTransparencyPdf('lista-asistencia.pdf'))
        ->toMediaCollection('documents');

    $internoCustom = TransparencyRecord::factory()->interno()->create([
        'name' => 'Oficio Reservado Nominal',
        'reference_number' => 'SIUT-INT/099/2023',
        'summary' => 'Incluye padrón nominal.',
        'observations' => 'Contiene datos personales de agremiados.',
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2023,
        'period' => null,
        'document_date' => '2023-04-02',
        'created_by' => $author->id,
    ]);
    $internoMediaA = $internoCustom->addMedia(createFakeTransparencyPdf('INTERNO_A.pdf'))
        ->toMediaCollection('documents');
    $internoMediaB = $internoCustom->addMedia(createFakeTransparencyPdf('INTERNO_B.pdf'))
        ->toMediaCollection('documents');

    $response = $this->get(route('transparency.type', ['type' => 'oficios']));

    $response->assertSuccessful();
    $response->assertSee('ITSM-SIUTITSM/031/04/2023');
    $response->assertSee('27 abr., 2023');
    $response->assertSee('Comisión ante dirección académica.');
    $response->assertSee('Descargar documento');
    $response->assertSee(route('transparency.documents.download', [$publicSingle, $singleMedia]));
    $response->assertDontSee('ESCANER_RAW_0042.pdf');
    $response->assertDontSee('Nota legacy oculta en público');

    $response->assertSee('acta-asamblea.pdf');
    $response->assertSee('lista-asistencia.pdf');
    $response->assertSee(route('transparency.documents.download', [$publicMulti, $multiFirst]));
    $response->assertSee(route('transparency.documents.download', [$publicMulti, $multiSecond]));

    $response->assertSee('SIUT-INT/099/2023');
    $response->assertSee('Motivo de acceso interno');
    $response->assertSee('Contiene datos personales de agremiados.');
    $response->assertSee('Solo consulta interna');
    $response->assertDontSee('INTERNO_A.pdf');
    $response->assertDontSee('INTERNO_B.pdf');
    $response->assertDontSee(route('transparency.documents.download', [$internoCustom, $internoMediaA]));
    $response->assertDontSee(route('transparency.documents.download', [$internoCustom, $internoMediaB]));
});

test('financiero cards do not render document date even if populated', function () {
    $author = User::factory()->create();

    TransparencyRecord::factory()->interno()->create([
        'name' => 'Balance Anual 2025',
        'type' => TransparencyRecordType::Financiero,
        'fiscal_year' => 2025,
        'period' => 'Anual',
        'document_date' => '2025-03-15',
        'created_by' => $author->id,
    ]);

    $response = $this->get(route('transparency.type', ['type' => 'financiero']));

    $response->assertSuccessful();
    $response->assertDontSee('Fecha del Documento:');
});

test('public transparency page renders client-side normalized search input, card index, empty state, and ponytail comment', function () {
    $author = User::factory()->create();

    TransparencyRecord::factory()->interno()->create([
        'name' => 'Oficio de Gestión Académica',
        'reference_number' => 'SIUT-2025/044',
        'summary' => 'Revisión de cláusula séptima del convenio.',
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-08-19',
        'created_by' => $author->id,
    ]);

    $response = $this->get(route('transparency.type', ['type' => 'oficios']));

    $response->assertSuccessful();
    $response->assertSee('data-transparency-search', false);
    $response->assertSee('data-search-empty', false);
    $response->assertSee('data-year-group', false);
    $response->assertSee('data-subgroup', false);
    $response->assertSee('data-record-card', false);
    $response->assertSee('data-search="oficio de gestion academica siut-2025/044 revision de clausula septima del convenio."', false);
    $response->assertSee('// ponytail:', false);
    $response->assertDontSee('fetch(', false);
});
