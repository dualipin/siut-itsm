<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Enums\UserRole;
use App\Filament\Resources\TransparencyRecords\Pages\CreateTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\Pages\EditTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\Pages\ViewTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\TransparencyRecordResource;
use App\Models\TransparencyRecord;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Smalot\PdfParser\Parser;

beforeEach(function () {
    Storage::fake('public');
    Cache::flush();
    Config::set('services.document_ai.enabled', false);
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('create and edit forms render documentos adjuntos before informacion del registro and visibilidad', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $record = TransparencyRecord::factory()->withDocuments(1)->create([
        'created_by' => $admin->id,
    ]);

    Livewire::test(CreateTransparencyRecord::class)
        ->assertSuccessful()
        ->assertSeeInOrder([
            'Documentos Adjuntos',
            'Información del Registro',
            'Visibilidad en el Portal',
        ]);

    Livewire::test(EditTransparencyRecord::class, ['record' => $record->getKey()])
        ->assertSuccessful()
        ->assertSeeInOrder([
            'Documentos Adjuntos',
            'Información del Registro',
            'Visibilidad en el Portal',
        ]);
});

test('create form shows only contextual fields matching selected category and visibility', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $component = Livewire::test(CreateTransparencyRecord::class)
        ->assertSuccessful()
        ->assertFormSet([
            'status' => TransparencyRecordStatus::Interno,
            'document_date' => null,
        ])
        ->assertFormFieldExists('type')
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('summary')
        ->assertFormFieldExists('documents')
        ->assertFormFieldExists('status')
        ->assertFormFieldVisible('observations')
        ->assertFormFieldHidden('fiscal_year')
        ->assertFormFieldHidden('period')
        ->assertFormFieldHidden('document_date')
        ->assertFormFieldHidden('reference_number');

    // Selecting Financiero reveals fiscal_year (default current year) and period, hides document_date and reference_number
    $component
        ->set('data.type', TransparencyRecordType::Financiero->value)
        ->assertFormFieldVisible('fiscal_year')
        ->assertFormFieldVisible('period')
        ->assertFormFieldHidden('document_date')
        ->assertFormFieldHidden('reference_number')
        ->assertSee(TransparencyRecordType::Financiero->getDescription());

    // Selecting Oficios reveals document_date and reference_number, hides fiscal_year and period
    $component
        ->set('data.type', TransparencyRecordType::Oficios->value)
        ->assertFormFieldHidden('fiscal_year')
        ->assertFormFieldHidden('period')
        ->assertFormFieldVisible('document_date')
        ->assertFormFieldVisible('reference_number')
        ->assertSee(TransparencyRecordType::Oficios->getDescription());

    // Selecting Otros reveals document_date, hides fiscal_year, period, and reference_number
    $component
        ->set('data.type', TransparencyRecordType::Otros->value)
        ->assertFormFieldHidden('fiscal_year')
        ->assertFormFieldHidden('period')
        ->assertFormFieldVisible('document_date')
        ->assertFormFieldHidden('reference_number');

    // Switching visibility to Publico hides observations
    $component
        ->set('data.status', TransparencyRecordStatus::Publico->value)
        ->assertFormFieldHidden('observations')
        ->assertSee(TransparencyRecordStatus::Publico->getDescription());
});

test('non-financiero records derive fiscal_year from document_date and closed period list rejects free text', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    // Closed period list rejects free-text period in Financiero
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'name' => 'Reporte con periodo inválido',
            'fiscal_year' => 2025,
            'period' => 'Periodo Inventado',
            'status' => TransparencyRecordStatus::Interno->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['period']);

    // Oficios derives fiscal_year automatically from document_date without showing fiscal_year
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Oficios->value,
            'name' => 'Oficio de Gestión 2024',
            'document_date' => '2024-08-19',
            'reference_number' => 'SIUT/088/2024',
            'observations' => 'Pendiente de firma',
            'status' => TransparencyRecordStatus::Interno->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'name' => 'Oficio de Gestión 2024',
        'type' => TransparencyRecordType::Oficios->value,
        'fiscal_year' => 2024,
        'period' => null,
        'reference_number' => 'SIUT/088/2024',
        'observations' => 'Pendiente de firma',
        'status' => TransparencyRecordStatus::Interno->value,
    ]);
});

test('editing category or visibility clears out-of-context fields to null', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    // Start as Oficios + Interno with folio, document_date, and observations
    $record = TransparencyRecord::factory()->withDocuments(1)->create([
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2023,
        'period' => null,
        'document_date' => '2023-05-12',
        'reference_number' => 'SIUT/012/2023',
        'status' => TransparencyRecordStatus::Interno,
        'observations' => 'Datos personales internos',
        'created_by' => $admin->id,
    ]);

    // Switch to Financiero + Publico: clears document_date, reference_number, and observations
    Livewire::test(EditTransparencyRecord::class, ['record' => $record->getKey()])
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'fiscal_year' => 2025,
            'period' => '2do Trimestre',
            'status' => TransparencyRecordStatus::Publico->value,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $record->refresh();
    expect($record->type)->toBe(TransparencyRecordType::Financiero)
        ->and($record->status)->toBe(TransparencyRecordStatus::Publico)
        ->and($record->fiscal_year)->toBe(2025)
        ->and($record->period)->toBe('2do Trimestre')
        ->and($record->document_date)->toBeNull()
        ->and($record->reference_number)->toBeNull()
        ->and($record->observations)->toBeNull();

    // Switch from Financiero to Otros: clears period and derives fiscal_year from document_date
    Livewire::test(EditTransparencyRecord::class, ['record' => $record->getKey()])
        ->fillForm([
            'type' => TransparencyRecordType::Otros->value,
            'document_date' => '2022-01-01',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $record->refresh();
    expect($record->type)->toBe(TransparencyRecordType::Otros)
        ->and($record->fiscal_year)->toBe(2022)
        ->and($record->period)->toBeNull()
        ->and($record->reference_number)->toBeNull();
});

test('create page keeps createAnother enabled and creates consecutive records', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $component = Livewire::test(CreateTransparencyRecord::class)
        ->assertSee('Crear y crear otro');

    expect($component->instance()->canCreateAnother())->toBeTrue();

    $component
        ->fillForm([
            'type' => TransparencyRecordType::Actas->value,
            'name' => 'Acta Ordinaria Enero 2025',
            'document_date' => '2025-01-20',
            'status' => TransparencyRecordStatus::Interno->value,
        ])
        ->call('createAnother')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'name' => 'Acta Ordinaria Enero 2025',
        'type' => TransparencyRecordType::Actas->value,
        'fiscal_year' => 2025,
    ]);
});

test('reference_number is optional allowing multiple nulls and rejects duplicate non-empty values', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Oficios->value,
            'name' => 'Oficio Sin Folio A',
            'document_date' => '2025-02-01',
            'reference_number' => '',
            'status' => TransparencyRecordStatus::Interno->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Legal->value,
            'name' => 'Norma Sin Folio B',
            'document_date' => '2025-02-02',
            'reference_number' => null,
            'status' => TransparencyRecordStatus::Interno->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $existing = TransparencyRecord::factory()->create([
        'type' => TransparencyRecordType::Oficios,
        'document_date' => '2025-03-01',
        'fiscal_year' => 2025,
        'period' => null,
        'reference_number' => 'SIUT-UNICO-001',
        'created_by' => $admin->id,
    ]);

    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Actas->value,
            'name' => 'Acta con folio duplicado',
            'document_date' => '2025-03-05',
            'reference_number' => '  SIUT-UNICO-001  ',
            'status' => TransparencyRecordStatus::Interno->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['reference_number']);

    Livewire::test(EditTransparencyRecord::class, ['record' => $existing->getKey()])
        ->fillForm([
            'name' => 'Oficio Actualizado Mismo Folio',
            'reference_number' => 'SIUT-UNICO-001',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
});

test('publico requires at least one file and removing all files on edit downgrades publico to interno', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    // Block creating Publico with 0 files
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'name' => 'Balance Público Sin Archivo',
            'fiscal_year' => 2025,
            'period' => 'Anual',
            'status' => TransparencyRecordStatus::Publico->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['status']);

    expect(TransparencyRecord::where('name', 'Balance Público Sin Archivo')->exists())->toBeFalse();

    // Allow creating Interno with 0 files, then block switching to Publico without attaching a file
    $interno = TransparencyRecord::factory()->interno()->create([
        'type' => TransparencyRecordType::Oficios,
        'document_date' => '2025-04-01',
        'fiscal_year' => 2025,
        'period' => null,
        'created_by' => $admin->id,
    ]);

    Livewire::test(EditTransparencyRecord::class, ['record' => $interno->getKey()])
        ->fillForm([
            'status' => TransparencyRecordStatus::Publico->value,
        ])
        ->call('save')
        ->assertHasFormErrors(['status']);

    expect($interno->fresh()->status)->toBe(TransparencyRecordStatus::Interno);

    // Creating Publico with a valid PDF succeeds
    $pdf = UploadedFile::fake()->create('balance-2025.pdf', 200, 'application/pdf');
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'name' => 'Balance Público Con Archivo',
            'fiscal_year' => 2025,
            'period' => 'Anual',
            'status' => TransparencyRecordStatus::Publico->value,
            'documents' => [$pdf],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = TransparencyRecord::where('name', 'Balance Público Con Archivo')->firstOrFail();
    expect($created->status)->toBe(TransparencyRecordStatus::Publico)
        ->and($created->getMedia('documents'))->toHaveCount(1);

    // Removing all files from a Publico record on edit auto-downgrades to Interno with warning notification
    Livewire::test(EditTransparencyRecord::class, ['record' => $created->getKey()])
        ->set('data.documents', [])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Visibilidad cambiada a Consulta Interna');

    expect($created->fresh()->status)->toBe(TransparencyRecordStatus::Interno)
        ->and($created->fresh()->getMedia('documents'))->toHaveCount(0);
});

test('files over 15 MB and formats outside category are rejected with Spanish messages', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    // File > 15 MB (15361 KB) rejected with Spanish message
    $oversizedPdf = UploadedFile::fake()->create('enorme.pdf', 15361, 'application/pdf');
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Oficios->value,
            'name' => 'Oficio con archivo pesado',
            'document_date' => '2025-05-01',
            'status' => TransparencyRecordStatus::Interno->value,
            'documents' => [$oversizedPdf],
        ])
        ->call('create')
        ->assertHasFormErrors(['documents'])
        ->assertSee('Cada archivo adjunto no debe superar los 15 MB.');

    $livewireOversized = UploadedFile::fake()->create('demasiado-grande.pdf', 20481, 'application/pdf');
    $validator = Validator::make(
        ['files' => [$livewireOversized]],
        ['files.*' => config('livewire.temporary_file_upload.rules')],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('files.0'))
        ->toBe('El campo archivo no debe pesar más de 20480 kilobytes.');

    // Spreadsheet (.xlsx) rejected in Oficios
    $xlsxForOficios = UploadedFile::fake()->create(
        'anexo.xlsx',
        120,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Oficios->value,
            'name' => 'Oficio con Excel inválido',
            'document_date' => '2025-05-01',
            'status' => TransparencyRecordStatus::Interno->value,
            'documents' => [$xlsxForOficios],
        ])
        ->call('create')
        ->assertHasFormErrors(['documents']);

    // Spreadsheets (.xlsx, .xls, .csv) are accepted when type is not selected yet (only type fails as required)
    $xlsxWithoutType = UploadedFile::fake()->create(
        'sin-tipo.xlsx',
        120,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );
    $xlsWithoutType = UploadedFile::fake()->create(
        'sin-tipo.xls',
        120,
        'application/vnd.ms-excel',
    );
    $csvWithoutType = UploadedFile::fake()->createWithContent('sin-tipo.csv', "concepto,monto\ningresos,1000\n");

    $preCategoryComponent = Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'name' => 'Registro sin categoría',
            'status' => TransparencyRecordStatus::Interno->value,
            'documents' => [$xlsxWithoutType, $xlsWithoutType, $csvWithoutType],
        ])
        ->call('create')
        ->assertHasFormErrors(['type' => 'required'])
        ->assertHasNoFormErrors(['documents']);

    // Selecting a non-Financiero category afterwards rejects the incompatible spreadsheets
    $preCategoryComponent
        ->fillForm([
            'type' => TransparencyRecordType::Oficios->value,
            'document_date' => '2025-05-01',
        ])
        ->call('create')
        ->assertHasFormErrors(['documents'])
        ->assertSee('El formato del archivo no está permitido para la categoría seleccionada.');

    // Switching to Financiero accepts those same pre-uploaded spreadsheets and persists them
    $preCategoryComponent
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'fiscal_year' => 2025,
            'period' => 'Anual',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TransparencyRecord::where('name', 'Registro sin categoría')->firstOrFail()->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION))
        ->toHaveCount(3);

    // Arbitrary .txt is still rejected when type is not selected yet
    $txtWithoutType = UploadedFile::fake()->createWithContent('notas-sin-tipo.txt', 'texto plano');
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'name' => 'Registro sin categoría con TXT',
            'status' => TransparencyRecordStatus::Interno->value,
            'documents' => [$txtWithoutType],
        ])
        ->call('create')
        ->assertHasFormErrors(['type', 'documents']);

    // Arbitrary .txt rejected in Financiero even though text/plain MIME is allowed for CSVs
    $txtForFinanciero = UploadedFile::fake()->createWithContent('notas.txt', 'texto plano');
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'name' => 'Financiero con TXT inválido',
            'fiscal_year' => 2025,
            'period' => 'Anual',
            'status' => TransparencyRecordStatus::Interno->value,
            'documents' => [$txtForFinanciero],
        ])
        ->call('create')
        ->assertHasFormErrors(['documents']);

    // Spreadsheet (.xlsx) and real text/plain .csv accepted in Financiero
    $xlsxForFinanciero = UploadedFile::fake()->create(
        'balanza-2025.xlsx',
        120,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );
    $csvForFinanciero = UploadedFile::fake()->createWithContent('desglose.csv', "concepto,monto\ningresos,1000\n");

    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'name' => 'Estado Financiero 2025',
            'fiscal_year' => 2025,
            'period' => '1er Trimestre',
            'status' => TransparencyRecordStatus::Publico->value,
            'documents' => [$xlsxForFinanciero, $csvForFinanciero],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TransparencyRecord::where('name', 'Estado Financiero 2025')->firstOrFail()->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION))
        ->toHaveCount(2);
});

test('fiscal_year accepts historical years prior to 1990', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'name' => 'Balance Histórico 1985',
            'fiscal_year' => 1985,
            'period' => 'Anual',
            'status' => TransparencyRecordStatus::Interno->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'name' => 'Balance Histórico 1985',
        'fiscal_year' => 1985,
    ]);
});

test('changing category from financiero to non-financial blocks saving while spreadsheet media remains attached', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $record = TransparencyRecord::factory()->interno()->create([
        'type' => TransparencyRecordType::Financiero,
        'fiscal_year' => 2025,
        'period' => 'Anual',
        'document_date' => null,
        'reference_number' => null,
        'created_by' => $admin->id,
    ]);

    $record->addMedia(UploadedFile::fake()->create(
        'balanza-anual.xlsx',
        120,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ))
        ->usingName('balanza-anual')
        ->usingFileName('balanza-anual.xlsx')
        ->toMediaCollection('documents');

    $component = Livewire::test(EditTransparencyRecord::class, ['record' => $record->getKey()])
        ->fillForm([
            'type' => TransparencyRecordType::Oficios->value,
            'document_date' => '2025-05-10',
        ])
        ->call('save')
        ->assertHasFormErrors(['type'])
        ->assertSee('balanza-anual.xlsx');

    expect($record->fresh()->type)->toBe(TransparencyRecordType::Financiero);

    // Removing the incompatible spreadsheet allows changing category to Oficios
    $component
        ->set('data.documents', [])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($record->fresh()->type)->toBe(TransparencyRecordType::Oficios)
        ->and($record->fresh()->getMedia('documents'))->toHaveCount(0);
});

test('view transparency record infolist displays only applicable metadata and direct open and download media links', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $financialPublic = TransparencyRecord::factory()->publico()->withDocuments(1)->create([
        'type' => TransparencyRecordType::Financiero,
        'fiscal_year' => 2025,
        'period' => '3er Trimestre',
        'document_date' => null,
        'reference_number' => null,
        'observations' => null,
        'created_by' => $admin->id,
    ]);

    $financialMedia = $financialPublic->getMedia('documents')->firstOrFail();
    $openUrl = route('portal.transparency.documents.show', ['record' => $financialPublic, 'media' => $financialMedia]);
    $downloadUrl = route('portal.transparency.documents.show', ['record' => $financialPublic, 'media' => $financialMedia, 'download' => 1]);

    Livewire::test(ViewTransparencyRecord::class, ['record' => $financialPublic->getKey()])
        ->assertSuccessful()
        ->assertSee('Año Fiscal')
        ->assertSee('2025')
        ->assertSee('Periodo')
        ->assertSee('3er Trimestre')
        ->assertDontSee('Fecha del Documento')
        ->assertDontSee('No. de Oficio / Folio')
        ->assertDontSee('Motivo de acceso interno')
        ->assertSee($financialMedia->file_name)
        ->assertSee('Abrir')
        ->assertSee('Descargar')
        ->assertSeeHtml($openUrl)
        ->assertSeeHtml(str_replace('=', '&#61;', $downloadUrl));

    $oficioInterno = TransparencyRecord::factory()->interno()->withDocuments(1)->create([
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2024,
        'period' => null,
        'document_date' => '2024-09-10',
        'reference_number' => 'SIUT/099/2024',
        'observations' => 'Contiene datos personales de agremiados.',
        'created_by' => $admin->id,
    ]);

    Livewire::test(ViewTransparencyRecord::class, ['record' => $oficioInterno->getKey()])
        ->assertSuccessful()
        ->assertSee('Fecha del Documento')
        ->assertSee('No. de Oficio / Folio')
        ->assertSee('SIUT/099/2024')
        ->assertSee('Motivo de acceso interno')
        ->assertSee('Contiene datos personales de agremiados.')
        ->assertDontSee('Año Fiscal')
        ->assertDontSee('Periodo');
});

test('lider can access transparency record pages while agremiado is forbidden', function () {
    $lider = User::factory()->create(['role' => UserRole::Lider, 'is_active' => true]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);
    $record = TransparencyRecord::factory()->withDocuments(1)->create();

    $this->actingAs($lider);
    $this->get(TransparencyRecordResource::getUrl('index'))->assertSuccessful();
    $this->get(TransparencyRecordResource::getUrl('create'))->assertSuccessful();
    $this->get(TransparencyRecordResource::getUrl('view', ['record' => $record]))->assertSuccessful();
    $this->get(TransparencyRecordResource::getUrl('edit', ['record' => $record]))->assertSuccessful();

    $this->actingAs($agremiado);
    $this->get(TransparencyRecordResource::getUrl('index'))->assertForbidden();
    $this->get(TransparencyRecordResource::getUrl('create'))->assertForbidden();
    $this->get(TransparencyRecordResource::getUrl('view', ['record' => $record]))->assertForbidden();
    $this->get(TransparencyRecordResource::getUrl('edit', ['record' => $record]))->assertForbidden();
});

function buildClientStyleMultipagePdf(int $pageCount): string
{
    $image = imagecreatetruecolor(16, 20);
    ob_start();
    imagejpeg($image, null, 75);
    $jpegBytes = (string) ob_get_clean();
    imagedestroy($image);

    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $totalObjects = 2 + $pageCount * 3;
    $offsets = array_fill(0, $totalObjects + 1, 0);
    $kids = [];

    for ($i = 0; $i < $pageCount; $i++) {
        $kids[] = (3 + $i * 3).' 0 R';
    }

    $offsets[1] = strlen($pdf);
    $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

    $offsets[2] = strlen($pdf);
    $pdf .= '2 0 obj'."\n".'<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>'."\nendobj\n";

    for ($i = 0; $i < $pageCount; $i++) {
        $pageObjId = 3 + $i * 3;
        $contentObjId = 4 + $i * 3;
        $imageObjId = 5 + $i * 3;
        $widthPt = '612';
        $heightPt = '765';

        $offsets[$pageObjId] = strlen($pdf);
        $pdf .= "{$pageObjId} 0 obj\n"
            ."<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$widthPt} {$heightPt}] "
            ."/Resources << /XObject << /Im1 {$imageObjId} 0 R >> /ProcSet [/PDF /ImageB /ImageC] >> "
            ."/Contents {$contentObjId} 0 R >>\nendobj\n";

        $contentStream = "q\n{$widthPt} 0 0 {$heightPt} 0 0 cm\n/Im1 Do\nQ\n";
        $offsets[$contentObjId] = strlen($pdf);
        $pdf .= "{$contentObjId} 0 obj\n<< /Length ".strlen($contentStream)." >>\nstream\n{$contentStream}endstream\nendobj\n";

        $offsets[$imageObjId] = strlen($pdf);
        $pdf .= "{$imageObjId} 0 obj\n"
            .'<< /Type /XObject /Subtype /Image /Width 16 /Height 20 '
            .'/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpegBytes)." >>\n"
            ."stream\n{$jpegBytes}\nendstream\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 ".($totalObjects + 1)."\n";
    $pdf .= "0000000000 65535 f \n";

    for ($objId = 1; $objId <= $totalObjects; $objId++) {
        $pdf .= str_pad((string) $offsets[$objId], 10, '0', STR_PAD_LEFT)." 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size ".($totalObjects + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

    return $pdf;
}

test('directiva sees document scanner trigger on create and edit forms but not on view infolist', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $lider = User::factory()->create(['role' => UserRole::Lider, 'is_active' => true]);
    $record = TransparencyRecord::factory()->withDocuments(1)->create(['created_by' => $admin->id]);

    foreach ([$admin, $lider] as $directivaUser) {
        $this->actingAs($directivaUser);

        Livewire::test(CreateTransparencyRecord::class)
            ->assertSuccessful()
            ->assertSee('Escanear documento')
            ->assertSeeHtml('data-transparency-documents-upload');

        Livewire::test(EditTransparencyRecord::class, ['record' => $record->getKey()])
            ->assertSuccessful()
            ->assertSee('Escanear documento')
            ->assertSeeHtml('data-transparency-documents-upload');
    }

    $this->actingAs($admin);
    Livewire::test(ViewTransparencyRecord::class, ['record' => $record->getKey()])
        ->assertSuccessful()
        ->assertDontSee('Escanear documento');
});

test('document scanner renders continuous camera viewfinder, torch hook, review switch, and fallback controls', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    Livewire::test(CreateTransparencyRecord::class)
        ->assertSuccessful()
        ->assertSeeHtml('x-ref="cameraVideo"')
        ->assertSeeHtml('toggleTorch()')
        ->assertSeeHtml('switchToReview()')
        ->assertSeeHtml('openCameraView()')
        ->assertSeeHtml('capture="environment"')
        ->assertSee('Tomar foto')
        ->assertSee('Seleccionar de galería')
        ->assertSee('Agregar más fotos')
        ->assertSee('Aplicar filtro a todas')
        ->assertSee('Blanco y negro')
        ->assertSee('Elimina una o más páginas o cambia al filtro Blanco y negro para reducir el peso.')
        ->assertSeeHtml('cameraSessionId')
        ->assertSeeHtml('sessionId')
        ->assertSeeHtml('reprocessPageAt(')
        ->assertSeeHtml(':disabled="isExporting || processingCount > 0"')
        ->assertDontSeeHtml('$nextTick(() =>')
        ->assertSeeHtml('setPageFilter(')
        ->assertSeeHtml('rotatePage(')
        ->assertSeeHtml('movePageBy(');
});

test('typescript buildPdfFromJpegPages emits valid PDF 1.4 verified by Smalot PdfParser and persists via Livewire upload', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $portraitImg = imagecreatetruecolor(120, 160);
    ob_start();
    imagejpeg($portraitImg, null, 75);
    $portraitJpeg = (string) ob_get_clean();
    imagedestroy($portraitImg);

    $landscapeImg = imagecreatetruecolor(160, 120);
    ob_start();
    imagejpeg($landscapeImg, null, 75);
    $landscapeJpeg = (string) ob_get_clean();
    imagedestroy($landscapeImg);

    $payload = json_encode([
        ['jpegBase64' => base64_encode($portraitJpeg), 'width' => 120, 'height' => 160],
        ['jpegBase64' => base64_encode($landscapeJpeg), 'width' => 160, 'height' => 120],
    ], JSON_THROW_ON_ERROR);

    $nodeScript = implode("\n", [
        'import fs from "node:fs";',
        'import { buildPdfFromJpegPages } from "./resources/js/scanner/pdf.ts";',
        'const input = JSON.parse(fs.readFileSync(0, "utf8"));',
        'const pages = input.map((p) => ({ jpegBytes: Uint8Array.from(Buffer.from(p.jpegBase64, "base64")), width: p.width, height: p.height }));',
        'process.stdout.write(Buffer.from(buildPdfFromJpegPages(pages)));',
    ]);

    $result = Process::path(base_path())
        ->input($payload)
        ->run(['node', '--input-type=module', '-e', $nodeScript]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    $pdfBytes = $result->output();
    $parsed = (new Parser)->parseContent($pdfBytes);
    $pages = $parsed->getPages();

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->getDetails()['MediaBox'])->toEqual([0, 0, 612, 816])
        ->and($pages[1]->getDetails()['MediaBox'])->toEqual([0, 0, 612, 459])
        ->and($pages[0]->getDetails()['Resources']['XObject']['Im1']['Filter'])->toBe('DCTDecode')
        ->and($pages[1]->getDetails()['Resources']['XObject']['Im1']['Filter'])->toBe('DCTDecode');

    $uploadedPdf = UploadedFile::fake()->createWithContent('escaneo-ts-real.pdf', $pdfBytes);

    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Oficios->value,
            'name' => 'Oficio Generado Desde TS',
            'document_date' => '2025-08-10',
            'reference_number' => 'SIUT-TS-PDF-001',
            'status' => TransparencyRecordStatus::Publico->value,
            'documents' => [$uploadedPdf],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $record = TransparencyRecord::where('name', 'Oficio Generado Desde TS')->firstOrFail();
    $media = $record->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION)->firstOrFail();
    $storedContent = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());
    $storedParsed = (new Parser)->parseContent($storedContent);
    $storedPages = $storedParsed->getPages();

    expect($storedPages)->toHaveCount(2)
        ->and($storedPages[0]->getDetails()['MediaBox'])->toEqual([0, 0, 612, 816])
        ->and($storedPages[1]->getDetails()['MediaBox'])->toEqual([0, 0, 612, 459]);
});

test('multi-page scanned PDF 1.4 attaches across all categories, persists in medialibrary, respects visibility, and rejects over 15 MB', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $pdfContent = buildClientStyleMultipagePdf(3);
    $parsed = (new Parser)->parseContent($pdfContent);
    expect($parsed->getPages())->toHaveCount(3);

    foreach (TransparencyRecordType::cases() as $index => $type) {
        $scannedPdf = UploadedFile::fake()->createWithContent("escaneo-{$type->value}.pdf", $pdfContent);

        $formData = [
            'type' => $type->value,
            'name' => "Registro Escaneado {$type->value}",
            'status' => TransparencyRecordStatus::Publico->value,
            'documents' => [$scannedPdf],
        ];

        if ($type->usesPeriod()) {
            $formData['fiscal_year'] = 2025;
            $formData['period'] = 'Anual';
        } else {
            $formData['document_date'] = '2025-06-15';
        }

        if ($type->usesFolio()) {
            $formData['reference_number'] = "SIUT-SCAN-{$index}";
        }

        Livewire::test(CreateTransparencyRecord::class)
            ->fillForm($formData)
            ->call('create')
            ->assertHasNoFormErrors();

        $created = TransparencyRecord::where('name', "Registro Escaneado {$type->value}")->firstOrFail();
        $media = $created->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION);

        expect($created->isPublico())->toBeTrue()
            ->and($media)->toHaveCount(1)
            ->and($media->first()->name)->toBe("escaneo-{$type->value}")
            ->and($media->first()->mime_type)->toBe('application/pdf');

        $storedContent = Storage::disk($media->first()->disk)->get($media->first()->getPathRelativeToRoot());
        $storedParsed = (new Parser)->parseContent($storedContent);
        expect($storedParsed->getPages())->toHaveCount(3);
    }

    // Attaching a scanned PDF in Interno keeps Interno visibility and observations
    $internoPdf = UploadedFile::fake()->createWithContent('interno-escaneado.pdf', $pdfContent);
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Oficios->value,
            'name' => 'Oficio Interno Escaneado',
            'document_date' => '2025-07-01',
            'observations' => 'Contiene firmas autógrafas',
            'status' => TransparencyRecordStatus::Interno->value,
            'documents' => [$internoPdf],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $internoRecord = TransparencyRecord::where('name', 'Oficio Interno Escaneado')->firstOrFail();
    expect($internoRecord->isInterno())->toBeTrue()
        ->and($internoRecord->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION))->toHaveCount(1);

    // Reject scanned PDF exceeding 15 MB
    $oversized = UploadedFile::fake()->create('escaneo-enorme.pdf', 15361, 'application/pdf');
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Actas->value,
            'name' => 'Acta Escaneada Excedida',
            'document_date' => '2025-07-02',
            'status' => TransparencyRecordStatus::Interno->value,
            'documents' => [$oversized],
        ])
        ->call('create')
        ->assertHasFormErrors(['documents']);
});

function buildFormDigitalTextPdf(string $text): string
{
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $stream = "BT\n/F1 12 Tf\n72 720 Td\n({$escaped}) Tj\nET\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        5 => '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
    ];

    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
    $pdf .= "0000000000 65535 f \n";

    foreach ($objects as $id => $body) {
        $pdf .= str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT)." 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

    return $pdf;
}

test('autofill_with_ai action visibility respects AI config, attachment presence, active directiva authorization, and never appears on view', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $lider = User::factory()->create(['role' => UserRole::Lider, 'is_active' => true]);
    $inactiveAdmin = User::factory()->admin()->create(['is_active' => false]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado, 'is_active' => true]);

    $recordWithDoc = TransparencyRecord::factory()->withDocuments(1)->create(['created_by' => $admin->id]);
    $pdf = UploadedFile::fake()->createWithContent('oficio.pdf', buildFormDigitalTextPdf(str_repeat('Oficio sindical de prueba. ', 4)));

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio sindical de prueba',
                        'summary' => 'Documento sindical de prueba.',
                        'document_date' => '2025-01-10',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => null,
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.9,
                    ]),
                ],
            ]],
        ], 200),
    ]);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    foreach ([$admin, $lider] as $directivaUser) {
        $this->actingAs($directivaUser);

        Livewire::test(CreateTransparencyRecord::class)
            ->assertDontSee('Autocompletar con IA')
            ->assertActionDoesNotExist($aiAction)
            ->fillForm(['documents' => [$pdf]])
            ->assertActionVisible($aiAction)
            ->assertSee('Autocompletar con IA');

        Livewire::test(EditTransparencyRecord::class, ['record' => $recordWithDoc->getKey()])
            ->assertActionVisible($aiAction)
            ->assertSee('Autocompletar con IA');
    }

    Config::set('services.document_ai.enabled', false);
    $this->actingAs($admin);
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm(['documents' => [$pdf]])
        ->assertDontSee('Autocompletar con IA')
        ->assertActionDoesNotExist($aiAction);

    Config::set('services.document_ai.enabled', true);
    Livewire::test(ViewTransparencyRecord::class, ['record' => $recordWithDoc->getKey()])
        ->assertDontSee('Autocompletar con IA');

    foreach ([$inactiveAdmin, $agremiado] as $unauthorizedUser) {
        $this->actingAs($unauthorizedUser);
        $this->get(TransparencyRecordResource::getUrl('create'))->assertForbidden();
        $this->get(TransparencyRecordResource::getUrl('edit', ['record' => $recordWithDoc]))->assertForbidden();
    }
});

test('autofill_with_ai on create analyzes digital PDF, fills contextual fields, keeps consulta interna, and persists', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $lider = User::factory()->create(['role' => UserRole::Lider, 'is_active' => true]);
    $this->actingAs($lider);

    $digitalPdf = UploadedFile::fake()->createWithContent(
        'oficio-comision.pdf',
        buildFormDigitalTextPdf(str_repeat('Oficio SIUT/101/2025 emitido por la Secretaria General al Director General el 15 de marzo de 2025. ', 2)),
    );

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio de comisión sindical dirigido a Dirección General',
                        'summary' => 'Oficio enviado por la Secretaría General a la Dirección General sobre comisión sindical.',
                        'document_date' => '2025-03-15',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'SIUT/101/2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.96,
                    ]),
                ],
            ]],
        ], 200),
    ]);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    $component = Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'documents' => [$digitalPdf],
        ])
        ->callAction($aiAction)
        ->assertNotified()
        ->assertFormSet([
            'type' => TransparencyRecordType::Oficios,
            'name' => 'Oficio de comisión sindical dirigido a Dirección General',
            'summary' => 'Oficio enviado por la Secretaría General a la Dirección General sobre comisión sindical.',
            'document_date' => '2025-03-15',
            'reference_number' => 'SIUT/101/2025',
            'period' => null,
            'status' => TransparencyRecordStatus::Interno,
        ]);

    Http::assertSent(function (Request $request): bool {
        $parts = $request['messages'][1]['content'] ?? [];

        return count($parts) === 1
            && ($parts[0]['type'] ?? null) === 'text'
            && str_contains((string) ($parts[0]['text'] ?? ''), 'SIUT/101/2025');
    });

    $component->call('create')->assertHasNoFormErrors();

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'type' => TransparencyRecordType::Oficios->value,
        'name' => 'Oficio de comisión sindical dirigido a Dirección General',
        'fiscal_year' => 2025,
        'reference_number' => 'SIUT/101/2025',
        'status' => TransparencyRecordStatus::Interno->value,
    ]);
});

test('autofill_with_ai on create optimizes image for Financiero, clears non-financial fields, and handles AI failure without losing uploads', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $largeImage = UploadedFile::fake()->image('balance-q2.png', 1800, 1200);
    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    Http::fakeSequence('ai.test/*')
        ->push(['error' => ['message' => 'Upstream error']], 500)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'financiero',
                        'name' => 'Estado financiero del segundo trimestre 2024',
                        'summary' => 'Balance general de ingresos y egresos sindicales del segundo trimestre.',
                        'document_date' => '2024-06-30',
                        'fiscal_year' => 2024,
                        'period' => '2do Trimestre',
                        'reference_number' => 'IGNORAR-FOLIO',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.93,
                    ]),
                ],
            ]],
        ], 200);

    $component = Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Oficios->value,
            'name' => 'Oficio previo capturado',
            'summary' => 'Resumen previo capturado.',
            'document_date' => '2024-06-30',
            'reference_number' => 'TEMP-FOLIO-01',
            'documents' => [$largeImage],
        ])
        ->callAction($aiAction)
        ->assertNotified('No se pudo autocompletar con IA');

    expect($component->get('data.documents'))->not->toBeEmpty();

    $component
        ->callAction($aiAction)
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Financiero,
            'name' => 'Estado financiero del segundo trimestre 2024',
            'fiscal_year' => 2024,
            'period' => '2do Trimestre',
            'document_date' => null,
            'reference_number' => null,
            'status' => TransparencyRecordStatus::Interno,
        ]);

    Http::assertSent(function (Request $request): bool {
        $parts = $request['messages'][1]['content'] ?? [];
        $dataUrl = (string) ($parts[1]['image_url']['url'] ?? '');

        if (! str_starts_with($dataUrl, 'data:image/jpeg;base64,')) {
            return false;
        }

        $decoded = base64_decode(substr($dataUrl, strlen('data:image/jpeg;base64,')), true);
        if (! is_string($decoded)) {
            return false;
        }

        [$width, $height] = getimagesizefromstring($decoded);

        return max($width, $height) === 1000;
    });

    $component->call('create')->assertHasNoFormErrors();

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'type' => TransparencyRecordType::Financiero->value,
        'name' => 'Estado financiero del segundo trimestre 2024',
        'fiscal_year' => 2024,
        'period' => '2do Trimestre',
        'document_date' => null,
        'reference_number' => null,
        'status' => TransparencyRecordStatus::Interno->value,
    ]);
});

test('autofill_with_ai on edit analyzes saved media UUID, clears out-of-context fields, uses cache on repeat, and saves', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $record = TransparencyRecord::factory()->interno()->create([
        'type' => TransparencyRecordType::Financiero,
        'fiscal_year' => 2024,
        'period' => 'Anual',
        'document_date' => null,
        'reference_number' => null,
        'created_by' => $admin->id,
    ]);

    $pdfContent = buildFormDigitalTextPdf(str_repeat('Acta de asamblea extraordinaria del SIUT-ITSM folio ACTA-02-2025 del 10 de febrero de 2025. ', 2));
    $record->addMedia(UploadedFile::fake()->createWithContent('acta-asamblea.pdf', $pdfContent))
        ->toMediaCollection(TransparencyRecord::DOCUMENTS_COLLECTION);

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'actas',
                        'name' => 'Acta de asamblea extraordinaria de febrero 2025',
                        'summary' => 'Minuta de acuerdos tomados en asamblea extraordinaria sindical.',
                        'document_date' => '2025-02-10',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'ACTA-02-2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.94,
                    ]),
                ],
            ]],
        ], 200),
    ]);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    $component = Livewire::test(EditTransparencyRecord::class, ['record' => $record->getKey()])
        ->callAction($aiAction)
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Actas,
            'name' => 'Acta de asamblea extraordinaria de febrero 2025',
            'document_date' => '2025-02-10',
            'reference_number' => 'ACTA-02-2025',
            'period' => null,
            'status' => TransparencyRecordStatus::Interno,
        ]);

    $component->callAction($aiAction)
        ->assertNotified('Metadatos propuestos con IA');

    Http::assertSentCount(1);

    $component->call('save')->assertHasNoFormErrors();

    $record->refresh();
    expect($record->type)->toBe(TransparencyRecordType::Actas)
        ->and($record->name)->toBe('Acta de asamblea extraordinaria de febrero 2025')
        ->and($record->fiscal_year)->toBe(2025)
        ->and($record->period)->toBeNull()
        ->and($record->reference_number)->toBe('ACTA-02-2025')
        ->and($record->status)->toBe(TransparencyRecordStatus::Interno);
});

test('autofill_with_ai keeps consulta interna, shows visual PII alert, and suggests observations on create and edit', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $piiPdf = UploadedFile::fake()->createWithContent(
        'expediente-personal.pdf',
        buildFormDigitalTextPdf(str_repeat('Oficio con CURP ABCD900101HDFR00 y domicilio particular del agremiado. ', 2)),
    );
    $editPiiPdf = UploadedFile::fake()->createWithContent(
        'expediente-edicion.pdf',
        buildFormDigitalTextPdf(str_repeat('Oficio medico con RFC ABCD900101XYZ y numero telefonico particular. ', 2)),
    );
    $cleanPdf = UploadedFile::fake()->createWithContent(
        'oficio-general.pdf',
        buildFormDigitalTextPdf(str_repeat('Oficio general de convocatoria a reunion informativa sindical sin datos privados. ', 2)),
    );

    Http::fakeSequence('ai.test/*')
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio de gestión de prestación sindical',
                        'summary' => 'Solicitud administrativa presentada por agremiado ante el comité.',
                        'document_date' => '2025-04-12',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'SIUT-PII-001',
                        'contains_personal_data' => true,
                        'internal_reason_suggestion' => 'Contiene CURP y domicilio particular del agremiado.',
                        'confidence' => 0.95,
                    ]),
                ],
            ]],
        ], 200)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio médico de incapacidad sindical',
                        'summary' => 'Gestión médica del agremiado ante el instituto.',
                        'document_date' => '2025-04-14',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'SIUT-PII-002',
                        'contains_personal_data' => true,
                        'internal_reason_suggestion' => 'Contiene RFC y datos médicos confidenciales.',
                        'confidence' => 0.94,
                    ]),
                ],
            ]],
        ], 200)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Convocatoria a reunión informativa',
                        'summary' => 'Aviso general de reunión informativa para personal sindicalizado.',
                        'document_date' => '2025-04-15',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'SIUT-CLEAN-002',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.92,
                    ]),
                ],
            ]],
        ], 200);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    // Even if status was switched to Publico before autofill, PII forces Consulta Interna, shows alert, and fills observations
    $createComponent = Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'status' => TransparencyRecordStatus::Publico->value,
            'documents' => [$piiPdf],
        ])
        ->callAction($aiAction)
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'status' => TransparencyRecordStatus::Interno,
            'observations' => 'Contiene CURP y domicilio particular del agremiado.',
        ])
        ->assertSee('Posibles datos personales sensibles detectados')
        ->assertSee('Contiene CURP y domicilio particular del agremiado.');

    $createComponent->call('create')->assertHasNoFormErrors();

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'reference_number' => 'SIUT-PII-001',
        'status' => TransparencyRecordStatus::Interno->value,
        'observations' => 'Contiene CURP y domicilio particular del agremiado.',
    ]);

    // On Edit of a Publico record, PII forces Interno and shows alert; re-running on clean doc hides alert and keeps Interno
    $publicRecord = TransparencyRecord::factory()->publico()->create([
        'type' => TransparencyRecordType::Oficios,
        'document_date' => '2025-04-01',
        'fiscal_year' => 2025,
        'period' => null,
        'observations' => null,
        'created_by' => $admin->id,
    ]);
    $publicRecord->addMedia($editPiiPdf)->toMediaCollection(TransparencyRecord::DOCUMENTS_COLLECTION);

    $editComponent = Livewire::test(EditTransparencyRecord::class, ['record' => $publicRecord->getKey()])
        ->assertDontSee('Posibles datos personales sensibles detectados')
        ->callAction($aiAction)
        ->assertFormSet([
            'status' => TransparencyRecordStatus::Interno,
            'observations' => 'Contiene RFC y datos médicos confidenciales.',
        ])
        ->assertSee('Posibles datos personales sensibles detectados');

    $editComponent->call('save')->assertHasNoFormErrors();

    expect($publicRecord->fresh()->status)->toBe(TransparencyRecordStatus::Interno)
        ->and($publicRecord->fresh()->observations)->toBe('Contiene RFC y datos médicos confidenciales.');

    // Re-running autofill with a clean document hides the previous PII alert and never switches to Publico automatically
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'documents' => [$piiPdf],
        ])
        ->callAction($aiAction)
        ->assertSee('Posibles datos personales sensibles detectados')
        ->fillForm([
            'documents' => [$cleanPdf],
        ])
        ->callAction($aiAction)
        ->assertFormSet([
            'status' => TransparencyRecordStatus::Interno,
        ])
        ->assertDontSee('Posibles datos personales sensibles detectados');
});

test('autofill_with_ai immediately warns when extracted reference_number duplicates another record on create and edit', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $otherRecord = TransparencyRecord::factory()->interno()->create([
        'type' => TransparencyRecordType::Oficios,
        'name' => 'Oficio Original Existente',
        'document_date' => '2025-01-10',
        'fiscal_year' => 2025,
        'period' => null,
        'reference_number' => 'SIUT/DUP/2025',
        'created_by' => $admin->id,
    ]);

    $ownRecord = TransparencyRecord::factory()->interno()->create([
        'type' => TransparencyRecordType::Actas,
        'name' => 'Acta Propia Existente',
        'document_date' => '2025-02-10',
        'fiscal_year' => 2025,
        'period' => null,
        'reference_number' => 'SIUT/OWN/2025',
        'created_by' => $admin->id,
    ]);

    $dupPdf = UploadedFile::fake()->createWithContent(
        'oficio-dup.pdf',
        buildFormDigitalTextPdf(str_repeat('Oficio con folio SIUT/DUP/2025 para deteccion inmediata de duplicados. ', 2)),
    );
    $ownPdf = UploadedFile::fake()->createWithContent(
        'acta-own.pdf',
        buildFormDigitalTextPdf(str_repeat('Acta propia con folio SIUT/OWN/2025 perteneciente al registro actual. ', 2)),
    );
    $finPdf = UploadedFile::fake()->createWithContent(
        'financiero-sin-folio.pdf',
        buildFormDigitalTextPdf(str_repeat('Balance financiero anual 2025 con referencia SIUT/DUP/2025 en texto. ', 2)),
    );

    $ownRecord->addMedia($ownPdf)->toMediaCollection(TransparencyRecord::DOCUMENTS_COLLECTION);

    Http::fakeSequence('ai.test/*')
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio con folio duplicado',
                        'summary' => 'Documento con folio ya existente.',
                        'document_date' => '2025-03-01',
                        'reference_number' => 'SIUT/DUP/2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.95,
                    ]),
                ],
            ]],
        ], 200)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'actas',
                        'name' => 'Acta Propia Existente Actualizada',
                        'summary' => 'Acta con su propio folio sin duplicidad.',
                        'document_date' => '2025-02-10',
                        'reference_number' => 'SIUT/OWN/2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.95,
                    ]),
                ],
            ]],
        ], 200)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'financiero',
                        'name' => 'Balance financiero anual 2025',
                        'summary' => 'Estado financiero sin folio aplicable.',
                        'fiscal_year' => 2025,
                        'period' => 'Anual',
                        'reference_number' => 'SIUT/DUP/2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.91,
                    ]),
                ],
            ]],
        ], 200);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    // On Create: warns immediately when extracted folio already exists in DB
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm(['documents' => [$dupPdf]])
        ->callAction($aiAction)
        ->assertNotified('Posible folio duplicado')
        ->assertFormSet([
            'reference_number' => $otherRecord->reference_number,
        ]);

    // On Edit: does NOT warn when extracted folio belongs to the current record itself
    Livewire::test(EditTransparencyRecord::class, ['record' => $ownRecord->getKey()])
        ->callAction($aiAction)
        ->assertNotNotified('Posible folio duplicado')
        ->assertFormSet([
            'reference_number' => 'SIUT/OWN/2025',
        ]);

    // On Edit: warns immediately when extracted folio belongs to another record
    Livewire::test(EditTransparencyRecord::class, ['record' => $ownRecord->getKey()])
        ->set('data.documents', [])
        ->fillForm(['documents' => [$dupPdf]])
        ->callAction($aiAction)
        ->assertNotified('Posible folio duplicado');

    // Non-folio category (Financiero) never triggers duplicate folio warning
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm(['documents' => [$finPdf]])
        ->callAction($aiAction)
        ->assertNotNotified('Posible folio duplicado');
});

test('autofill_with_ai uses fallback PII observation when suggestion is blank and preserves user observations when clean or empty-only', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $piiNoReasonPdf = UploadedFile::fake()->createWithContent(
        'pii-sin-sugerencia.pdf',
        buildFormDigitalTextPdf(str_repeat('Documento con salario y firma particular de agremiado sin nota adicional. ', 2)),
    );
    $cleanPdf = UploadedFile::fake()->createWithContent(
        'limpio-con-nota.pdf',
        buildFormDigitalTextPdf(str_repeat('Oficio sindical informativo sin datos personales para prueba de observaciones. ', 2)),
    );

    Http::fakeSequence('ai.test/*')
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Recibo sindical con datos salariales',
                        'summary' => 'Documento interno con información salarial.',
                        'document_date' => '2025-05-02',
                        'reference_number' => 'SIUT-FALLBACK-01',
                        'contains_personal_data' => true,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.90,
                    ]),
                ],
            ]],
        ], 200)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Circular informativa general',
                        'summary' => 'Circular general de difusión sindical.',
                        'document_date' => '2025-05-03',
                        'reference_number' => 'SIUT-CLEAN-OBS-02',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.93,
                    ]),
                ],
            ]],
        ], 200);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    // Blank internal_reason_suggestion still populates a clear fallback observation and shows PII alert
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'documents' => [$piiNoReasonPdf],
        ])
        ->callAction($aiAction)
        ->assertFormSet([
            'status' => TransparencyRecordStatus::Interno,
            'observations' => 'Contiene datos personales sensibles; se mantiene en Consulta Interna.',
        ])
        ->assertSee('Posibles datos personales sensibles detectados');

    // Clean document does not clear user-typed observations and keeps status Interno
    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'observations' => 'Motivo capturado manualmente por la Directiva.',
            'documents' => [$cleanPdf],
        ])
        ->callAction($aiAction)
        ->assertFormSet([
            'status' => TransparencyRecordStatus::Interno,
            'observations' => 'Motivo capturado manualmente por la Directiva.',
        ])
        ->assertDontSee('Posibles datos personales sensibles detectados');
});

function buildFormSyntheticXlsx(): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'form_xlsx_');
    $zip = new ZipArchive;
    $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString(
        'xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets><sheet name="Balanza Q3" sheetId="1" r:id="rId1"/></sheets>'
        .'</workbook>',
    );
    $zip->addFromString(
        'xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        .'</Relationships>',
    );
    $zip->addFromString(
        'xl/sharedStrings.xml',
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="2" uniqueCount="2">'
        .'<si><t>Balanza financiera tercer trimestre 2025</t></si>'
        .'<si><t>Ingresos sindicales netos</t></si>'
        .'</sst>',
    );
    $zip->addFromString(
        'xl/worksheets/sheet1.xml',
        '<?xml version="1.0" encoding="UTF-8"?>'
        .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        .'<sheetData>'
        .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="inlineStr"><is><t>Monto</t></is></c></row>'
        .'<row r="2"><c r="A2" t="s"><v>1</v></c><c r="B2"><v>98500.25</v></c></row>'
        .'</sheetData>'
        .'</worksheet>',
    );
    $zip->close();

    $bytes = (string) file_get_contents($tmp);
    unlink($tmp);

    return $bytes;
}

test('autofill_with_ai on create extracts page 1 JPEG from multipage DCTDecode PDF and saves', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $scannedPdf = UploadedFile::fake()->createWithContent('escaneo-3p.pdf', buildClientStyleMultipagePdf(3));

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio escaneado multipagina de gestion sindical',
                        'summary' => 'Oficio digitalizado en tres paginas mediante escaner.',
                        'document_date' => '2025-05-18',
                        'reference_number' => 'SIUT-MP-03',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.92,
                    ]),
                ],
            ]],
        ], 200),
    ]);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    $component = Livewire::test(CreateTransparencyRecord::class)
        ->fillForm(['documents' => [$scannedPdf]])
        ->callAction($aiAction)
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Oficios,
            'name' => 'Oficio escaneado multipagina de gestion sindical',
            'document_date' => '2025-05-18',
            'reference_number' => 'SIUT-MP-03',
            'status' => TransparencyRecordStatus::Interno,
        ]);

    Http::assertSent(function (Request $request): bool {
        $parts = $request['messages'][1]['content'] ?? [];
        $dataUrl = (string) ($parts[1]['image_url']['url'] ?? '');

        return count($parts) === 2
            && ($parts[1]['type'] ?? null) === 'image_url'
            && str_starts_with($dataUrl, 'data:image/jpeg;base64,');
    });

    $component->call('create')->assertHasNoFormErrors();

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'type' => TransparencyRecordType::Oficios->value,
        'name' => 'Oficio escaneado multipagina de gestion sindical',
        'reference_number' => 'SIUT-MP-03',
        'status' => TransparencyRecordStatus::Interno->value,
    ]);
});

test('autofill_with_ai on create extracts text-only from CSV and synthetic XLSX for Financiero and saves', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $csvFile = UploadedFile::fake()->createWithContent(
        'balanza-q1.csv',
        "Concepto,Trimestre,Monto\nCuotas sindicales,1er Trimestre,54000.00\n",
    );
    $xlsxFile = UploadedFile::fake()->createWithContent('balanza-q3.xlsx', buildFormSyntheticXlsx());

    Http::fakeSequence('ai.test/*')
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'financiero',
                        'name' => 'Balanza de cuotas del primer trimestre 2025',
                        'summary' => 'Detalle de cuotas sindicales en formato CSV.',
                        'fiscal_year' => 2025,
                        'period' => '1er Trimestre',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.94,
                    ]),
                ],
            ]],
        ], 200)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'financiero',
                        'name' => 'Balanza financiera tercer trimestre 2025',
                        'summary' => 'Libro contable del tercer trimestre en formato XLSX.',
                        'fiscal_year' => 2025,
                        'period' => '3er Trimestre',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.95,
                    ]),
                ],
            ]],
        ], 200);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    $csvComponent = Livewire::test(CreateTransparencyRecord::class)
        ->fillForm(['documents' => [$csvFile]])
        ->callAction($aiAction)
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Financiero,
            'fiscal_year' => 2025,
            'period' => '1er Trimestre',
            'status' => TransparencyRecordStatus::Interno,
        ]);

    $csvComponent->call('create')->assertHasNoFormErrors();

    $xlsxComponent = Livewire::test(CreateTransparencyRecord::class)
        ->fillForm(['documents' => [$xlsxFile]])
        ->callAction($aiAction)
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Financiero,
            'name' => 'Balanza financiera tercer trimestre 2025',
            'fiscal_year' => 2025,
            'period' => '3er Trimestre',
            'status' => TransparencyRecordStatus::Interno,
        ]);

    $xlsxComponent->call('create')->assertHasNoFormErrors();

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request): bool {
        $parts = $request['messages'][1]['content'] ?? [];

        return count($parts) === 1 && ($parts[0]['type'] ?? null) === 'text';
    });

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'name' => 'Balanza de cuotas del primer trimestre 2025',
        'period' => '1er Trimestre',
    ]);
    $this->assertDatabaseHas(TransparencyRecord::class, [
        'name' => 'Balanza financiera tercer trimestre 2025',
        'period' => '3er Trimestre',
    ]);
});

test('autofill_with_ai on create degrades cleanly on binary .xls without losing attachment and allows manual save', function () {
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $xlsBinary = UploadedFile::fake()->createWithContent(
        'historico-2020.xls',
        "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\x00", 512),
    );

    Http::fake();

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    $component = Livewire::test(CreateTransparencyRecord::class)
        ->fillForm(['documents' => [$xlsBinary]])
        ->callAction($aiAction)
        ->assertNotified('No se pudo autocompletar con IA');

    Http::assertNothingSent();
    expect($component->get('data.documents'))->not->toBeEmpty();

    $component->fillForm([
        'type' => TransparencyRecordType::Financiero->value,
        'name' => 'Reporte financiero historico 2020',
        'fiscal_year' => 2020,
        'period' => 'Anual',
        'status' => TransparencyRecordStatus::Interno->value,
    ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(TransparencyRecord::class, [
        'type' => TransparencyRecordType::Financiero->value,
        'name' => 'Reporte financiero historico 2020',
        'fiscal_year' => 2020,
        'period' => 'Anual',
    ]);
});

function enableTransparencyTestAi(): void
{
    Cache::flush();
    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-key',
        'model' => 'gemini-test',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'headers' => [],
    ]);
}

test('create auto-triggers AI once on first attachment, ignores additional attachments, never auto-triggers on edit, and resets on createAnother', function () {
    enableTransparencyTestAi();

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $firstPdf = UploadedFile::fake()->createWithContent(
        'oficio-inicial.pdf',
        buildFormDigitalTextPdf(str_repeat('Oficio SIUT/210/2025 emitido para solicitud de mantenimiento general. ', 2)),
    );
    $secondPdf = UploadedFile::fake()->createWithContent(
        'anexo-2.pdf',
        buildFormDigitalTextPdf(str_repeat('Anexo secundario que no debe disparar IA automáticamente. ', 2)),
    );
    $thirdPdf = UploadedFile::fake()->createWithContent(
        'anexo-3.pdf',
        buildFormDigitalTextPdf(str_repeat('Tercer anexo informativo sin disparo automático. ', 2)),
    );
    $nextRecordPdf = UploadedFile::fake()->createWithContent(
        'balance-siguiente.pdf',
        buildFormDigitalTextPdf(str_repeat('Estado financiero anual 2024 del sindicato SIUT-ITSM. ', 2)),
    );

    Http::fakeSequence('ai.test/*')
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio de solicitud de mantenimiento general',
                        'summary' => 'Oficio inicial enviado para solicitar mantenimiento general en instalaciones.',
                        'document_date' => '2025-04-02',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'SIUT/210/2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.95,
                    ]),
                ],
            ]],
        ], 200)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'financiero',
                        'name' => 'Estado financiero anual 2024',
                        'summary' => 'Resumen financiero anual del ejercicio fiscal 2024.',
                        'document_date' => null,
                        'fiscal_year' => 2024,
                        'period' => 'Anual',
                        'reference_number' => null,
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.92,
                    ]),
                ],
            ]],
        ], 200);

    // 1. First upload on Create auto-triggers AI once and fills contextual fields
    $component = Livewire::test(CreateTransparencyRecord::class)
        ->set('data.documents', [$firstPdf])
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Oficios,
            'name' => 'Oficio de solicitud de mantenimiento general',
            'summary' => 'Oficio inicial enviado para solicitar mantenimiento general en instalaciones.',
            'document_date' => '2025-04-02',
            'reference_number' => 'SIUT/210/2025',
            'status' => TransparencyRecordStatus::Interno,
        ]);

    Http::assertSentCount(1);

    // 2. Adding 2nd and 3rd attachments must NOT re-trigger AI automatically
    $component
        ->set('data.documents', [$secondPdf])
        ->set('data.documents', [$thirdPdf]);

    expect($component->get('data.documents'))->toHaveCount(3);
    Http::assertSentCount(1);

    // 3. createAnother resets the auto-trigger flag for the next record session
    $component->call('createAnother')->assertHasNoFormErrors();

    $component
        ->set('data.documents', [$nextRecordPdf])
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Financiero,
            'name' => 'Estado financiero anual 2024',
            'fiscal_year' => 2024,
            'period' => 'Anual',
            'status' => TransparencyRecordStatus::Interno,
        ]);

    Http::assertSentCount(2);

    // 4. Edit page never auto-triggers when adding a new attachment
    $existing = TransparencyRecord::factory()->withDocuments(1)->create([
        'created_by' => $admin->id,
    ]);

    $editExtraPdf = UploadedFile::fake()->createWithContent(
        'extra-edit.pdf',
        buildFormDigitalTextPdf(str_repeat('Documento extra agregado en edición. ', 3)),
    );

    Livewire::test(EditTransparencyRecord::class, ['record' => $existing->getKey()])
        ->set('data.documents.new_upload', $editExtraPdf);

    Http::assertSentCount(2);
});

test('create auto-trigger preserves user-entered fields and only fills empty metadata fields', function () {
    enableTransparencyTestAi();

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $pdf = UploadedFile::fake()->createWithContent(
        'oficio-parcial.pdf',
        buildFormDigitalTextPdf(str_repeat('Oficio SIUT/305/2025 dirigido a Dirección Académica el 18 de abril de 2025. ', 2)),
    );

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Nombre sugerido por IA que no debe pisar el del usuario',
                        'summary' => 'Resumen completado automáticamente por IA.',
                        'document_date' => '2025-04-18',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'SIUT/305/2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.91,
                    ]),
                ],
            ]],
        ], 200),
    ]);

    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'name' => 'Oficio capturado manualmente por usuario',
            'documents' => [$pdf],
        ])
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Oficios,
            'name' => 'Oficio capturado manualmente por usuario',
            'summary' => 'Resumen completado automáticamente por IA.',
            'document_date' => '2025-04-18',
            'reference_number' => 'SIUT/305/2025',
            'status' => TransparencyRecordStatus::Interno,
        ]);

    // When user pre-selected Financiero and fiscal_year, auto-trigger keeps Financiero and user fiscal_year even if AI says Oficios
    $financieroPdf = UploadedFile::fake()->createWithContent(
        'reporte-pre-categorizado.pdf',
        buildFormDigitalTextPdf(str_repeat('Reporte contable interno del ejercicio 2023. ', 3)),
    );

    Livewire::test(CreateTransparencyRecord::class)
        ->fillForm([
            'type' => TransparencyRecordType::Financiero->value,
            'fiscal_year' => 2023,
            'documents' => [$financieroPdf],
        ])
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Financiero,
            'fiscal_year' => 2023,
            'name' => 'Nombre sugerido por IA que no debe pisar el del usuario',
            'summary' => 'Resumen completado automáticamente por IA.',
            'document_date' => null,
            'reference_number' => null,
        ]);
});

test('autofill_with_ai executes directly with 1 file and empty metadata, and requests confirmation before overwriting filled metadata', function () {
    enableTransparencyTestAi();

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $pdf = UploadedFile::fake()->createWithContent(
        'acta-directa.pdf',
        buildFormDigitalTextPdf(str_repeat('Acta ordinaria de asamblea sindical del 12 de mayo de 2025. ', 2)),
    );

    Http::fakeSequence('ai.test/*')
        ->push(['error' => ['message' => 'Temporary outage']], 500)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'actas',
                        'name' => 'Acta ordinaria de mayo 2025',
                        'summary' => 'Minuta de la asamblea ordinaria celebrada en mayo de 2025.',
                        'document_date' => '2025-05-12',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'ACTA-05-2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.95,
                    ]),
                ],
            ]],
        ], 200);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    // Initial auto-trigger fails with 500, leaving metadata empty
    $component = Livewire::test(CreateTransparencyRecord::class)
        ->set('data.documents', [$pdf])
        ->assertNotified('No se pudo autocompletar con IA');

    // 1 file + empty metadata => mounting autofill_with_ai executes directly without modal
    $component
        ->mountAction($aiAction)
        ->assertActionNotMounted($aiAction)
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Actas,
            'name' => 'Acta ordinaria de mayo 2025',
            'reference_number' => 'ACTA-05-2025',
        ]);

    Http::assertSentCount(2);

    // User edits name; now metadata is filled => mounting autofill_with_ai opens confirmation modal
    $component
        ->fillForm([
            'name' => 'Nombre editado por el usuario',
        ])
        ->mountAction($aiAction)
        ->assertActionMounted($aiAction)
        ->assertMountedActionModalSee('¿Sobrescribir metadatos actuales con IA?');

    // Cancelling confirmation leaves user edits untouched and sends no extra HTTP requests
    $component
        ->unmountAction()
        ->assertActionNotMounted($aiAction)
        ->assertFormSet([
            'name' => 'Nombre editado por el usuario',
        ]);

    Http::assertSentCount(2);

    // Confirming overwrites with AI result (served from SHA-256 cache without extra HTTP call)
    $component
        ->mountAction($aiAction)
        ->assertActionMounted($aiAction)
        ->callMountedAction()
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'name' => 'Acta ordinaria de mayo 2025',
        ]);
});

test('autofill_with_ai shows file selector preselecting first file when 2 or more documents exist on create and edit', function () {
    enableTransparencyTestAi();

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    $firstPdf = UploadedFile::fake()->createWithContent(
        'primer-documento.pdf',
        buildFormDigitalTextPdf(str_repeat('CONTENIDO_ARCHIVO_UNO oficio inicial SIUT/001/2025. ', 2)),
    );
    $secondPdf = UploadedFile::fake()->createWithContent(
        'segundo-documento.pdf',
        buildFormDigitalTextPdf(str_repeat('CONTENIDO_ARCHIVO_DOS convenio colectivo legal SIUT/CONV-2025. ', 2)),
    );

    Http::fakeSequence('ai.test/*')
        ->push(['error' => ['message' => 'Initial failure']], 500)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'legal',
                        'name' => 'Convenio colectivo extraído del segundo archivo',
                        'summary' => 'Convenio analizado explícitamente al seleccionar el segundo archivo.',
                        'document_date' => '2025-06-01',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'SIUT/CONV-2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.94,
                    ]),
                ],
            ]],
        ], 200);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    // Create with 2 files (metadata still empty after failed auto-trigger on 1st file)
    $component = Livewire::test(CreateTransparencyRecord::class)
        ->set('data.documents', [$firstPdf])
        ->assertNotified('No se pudo autocompletar con IA')
        ->set('data.documents', [$secondPdf]);

    $component
        ->mountAction($aiAction)
        ->assertActionMounted($aiAction)
        ->assertActionDataSet(['selected_document' => 'doc:0'])
        ->assertMountedActionModalSee('1. primer-documento.pdf')
        ->assertMountedActionModalSee('2. segundo-documento.pdf')
        ->assertMountedActionModalDontSee('¿Sobrescribir metadatos actuales con IA?')
        ->setActionData(['selected_document' => 'doc:1'])
        ->callMountedAction()
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Legal,
            'name' => 'Convenio colectivo extraído del segundo archivo',
            'reference_number' => 'SIUT/CONV-2025',
        ]);

    Http::assertSent(function (Request $request): bool {
        $text = (string) ($request['messages'][1]['content'][0]['text'] ?? '');

        return str_contains($text, 'CONTENIDO_ARCHIVO_DOS')
            && ! str_contains($text, 'CONTENIDO_ARCHIVO_UNO');
    });
});

test('autofill_with_ai shows file selector and overwrite warning on edit with 2 or more saved documents', function () {
    enableTransparencyTestAi();

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'actas',
                        'name' => 'Acta extraída del segundo anexo guardado',
                        'summary' => 'Acta obtenida del segundo documento guardado en el registro.',
                        'document_date' => '2025-07-15',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'ACTA-07-2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.96,
                    ]),
                ],
            ]],
        ], 200),
    ]);

    $aiAction = TestAction::make('autofill_with_ai')->schemaComponent('documents-section');

    $record = TransparencyRecord::factory()->interno()->create([
        'type' => TransparencyRecordType::Oficios,
        'name' => 'Nombre previo guardado en BD',
        'document_date' => '2025-01-05',
        'fiscal_year' => 2025,
        'period' => null,
        'created_by' => $admin->id,
    ]);

    $mediaOne = $record->addMedia(UploadedFile::fake()->createWithContent(
        'guardado-uno.pdf',
        buildFormDigitalTextPdf(str_repeat('CONTENIDO_GUARDADO_UNO no debe enviarse si eligen el dos. ', 2)),
    ))->toMediaCollection(TransparencyRecord::DOCUMENTS_COLLECTION);

    $mediaTwo = $record->addMedia(UploadedFile::fake()->createWithContent(
        'guardado-dos.pdf',
        buildFormDigitalTextPdf(str_repeat('CONTENIDO_GUARDADO_DOS acta extraordinaria ACTA-07-2025. ', 2)),
    ))->toMediaCollection(TransparencyRecord::DOCUMENTS_COLLECTION);

    $editComponent = Livewire::test(EditTransparencyRecord::class, ['record' => $record->getKey()]);

    $editComponent
        ->mountAction($aiAction)
        ->assertActionMounted($aiAction)
        ->assertActionDataSet(['selected_document' => 'doc:0'])
        ->assertMountedActionModalSee('1. guardado-uno.pdf')
        ->assertMountedActionModalSee('2. guardado-dos.pdf')
        ->assertMountedActionModalSee('sobrescritos');

    $editComponent
        ->setActionData(['selected_document' => 'doc:1'])
        ->callMountedAction()
        ->assertNotified('Metadatos propuestos con IA')
        ->assertFormSet([
            'type' => TransparencyRecordType::Actas,
            'name' => 'Acta extraída del segundo anexo guardado',
            'reference_number' => 'ACTA-07-2025',
        ]);

    Http::assertSent(function (Request $request): bool {
        $text = (string) ($request['messages'][1]['content'][0]['text'] ?? '');

        return str_contains($text, 'CONTENIDO_GUARDADO_DOS')
            && ! str_contains($text, 'CONTENIDO_GUARDADO_UNO');
    });
});
