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
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('portal'));
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

    // File > 15 MB (15361 KB) rejected
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
        ->assertHasFormErrors(['documents']);

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

    // Spreadsheet (.xlsx) and .csv accepted in Financiero
    $xlsxForFinanciero = UploadedFile::fake()->create(
        'balanza-2025.xlsx',
        120,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );
    $csvForFinanciero = UploadedFile::fake()->create('desglose.csv', 40, 'text/csv');

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

    expect(TransparencyRecord::where('name', 'Estado Financiero 2025')->firstOrFail()->getMedia('documents'))
        ->toHaveCount(2);
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
