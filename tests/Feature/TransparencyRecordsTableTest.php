<?php

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Filament\Resources\TransparencyRecords\Pages\ListTransparencyRecords;
use App\Models\TransparencyRecord;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('admin table renders domain columns with fecha o periodo and attached files count', function () {
    $admin = User::factory()->admin()->create(['is_active' => true, 'name' => 'Secretario Finanzas']);

    $financiero = TransparencyRecord::factory()->interno()->withDocuments(2)->create([
        'name' => 'Balance General 2025',
        'reference_number' => null,
        'type' => TransparencyRecordType::Financiero,
        'fiscal_year' => 2025,
        'period' => '1er Trimestre',
        'document_date' => null,
        'created_by' => $admin->id,
    ]);

    $oficio = TransparencyRecord::factory()->publico()->withDocuments(1)->create([
        'name' => 'Oficio de Solicitud',
        'reference_number' => 'ITSM-SIUTITSM/031/05/2025',
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2025,
        'period' => null,
        'document_date' => '2025-05-10',
        'created_by' => $admin->id,
    ]);

    $otrosSinFecha = TransparencyRecord::factory()->interno()->create([
        'name' => 'Memoria Histórica',
        'reference_number' => null,
        'type' => TransparencyRecordType::Otros,
        'fiscal_year' => 2024,
        'period' => null,
        'document_date' => null,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListTransparencyRecords::class)
        ->assertSuccessful()
        ->assertTableColumnExists('reference_number', fn ($column) => $column->getLabel() === 'Folio')
        ->assertTableColumnExists('name', fn ($column) => $column->getLabel() === 'Nombre / Asunto')
        ->assertTableColumnExists('type', fn ($column) => $column->getLabel() === 'Categoría')
        ->assertTableColumnExists('date_or_period', fn ($column) => $column->getLabel() === 'Fecha / Periodo')
        ->assertTableColumnExists('status', fn ($column) => $column->getLabel() === 'Visibilidad')
        ->assertTableColumnExists('media_count', fn ($column) => $column->getLabel() === 'Archivos')
        ->assertTableColumnExists('creator.name', fn ($column) => $column->getLabel() === 'Subido por')
        ->assertTableColumnExists('created_at', fn ($column) => $column->getLabel() === 'Fecha de Creación')
        ->assertTableColumnExists('updated_at', fn ($column) => $column->getLabel() === 'Fecha de Actualización')
        ->assertTableColumnExists('deleted_at', fn ($column) => $column->getLabel() === 'Fecha de Eliminación')
        ->assertCanSeeTableRecords([$financiero, $oficio, $otrosSinFecha])
        ->assertTableColumnStateSet('date_or_period', '2025 · 1er Trimestre', record: $financiero)
        ->assertTableColumnStateSet('date_or_period', '10 may. 2025', record: $oficio)
        ->assertTableColumnStateSet('date_or_period', '2024', record: $otrosSinFecha)
        ->assertTableColumnStateSet('media_count', 2, record: $financiero)
        ->assertTableColumnStateSet('media_count', 1, record: $oficio)
        ->assertTableColumnStateSet('media_count', 0, record: $otrosSinFecha)
        ->assertTableColumnStateSet('status', TransparencyRecordStatus::Interno, record: $financiero)
        ->assertTableColumnStateSet('status', TransparencyRecordStatus::Publico, record: $oficio);
});

test('admin table searches by name and reference_number and filters by type, status, fiscal_year, and trashed', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);

    $financiero2024 = TransparencyRecord::factory()->interno()->create([
        'name' => 'Presupuesto Anual',
        'reference_number' => null,
        'type' => TransparencyRecordType::Financiero,
        'fiscal_year' => 2024,
        'period' => 'Anual',
        'created_by' => $admin->id,
    ]);

    $oficio2025 = TransparencyRecord::factory()->publico()->withDocuments(1)->create([
        'name' => 'Solicitud de Aula',
        'reference_number' => 'ITSM-SIUTITSM/099/2025',
        'type' => TransparencyRecordType::Oficios,
        'fiscal_year' => 2025,
        'document_date' => '2025-06-01',
        'created_by' => $admin->id,
    ]);

    $trashed2025 = TransparencyRecord::factory()->interno()->create([
        'name' => 'Acta Archivada',
        'reference_number' => 'ACTA-001/2025',
        'type' => TransparencyRecordType::Actas,
        'fiscal_year' => 2025,
        'document_date' => '2025-02-01',
        'created_by' => $admin->id,
    ]);
    $trashed2025->delete();

    $this->actingAs($admin);

    Livewire::test(ListTransparencyRecords::class)
        ->assertTableFilterExists('type', fn ($filter) => $filter->getLabel() === 'Categoría')
        ->assertTableFilterExists('status', fn ($filter) => $filter->getLabel() === 'Visibilidad')
        ->assertTableFilterExists('fiscal_year', fn ($filter) => $filter->getLabel() === 'Año')
        ->assertTableFilterExists('trashed')
        ->assertCanSeeTableRecords([$financiero2024, $oficio2025])
        ->assertCanNotSeeTableRecords([$trashed2025])
        ->searchTable('Presupuesto Anual')
        ->assertCanSeeTableRecords([$financiero2024])
        ->assertCanNotSeeTableRecords([$oficio2025])
        ->searchTable('ITSM-SIUTITSM/099/2025')
        ->assertCanSeeTableRecords([$oficio2025])
        ->assertCanNotSeeTableRecords([$financiero2024])
        ->searchTable('')
        ->filterTable('type', TransparencyRecordType::Financiero->value)
        ->assertCanSeeTableRecords([$financiero2024])
        ->assertCanNotSeeTableRecords([$oficio2025])
        ->removeTableFilter('type')
        ->filterTable('status', TransparencyRecordStatus::Publico->value)
        ->assertCanSeeTableRecords([$oficio2025])
        ->assertCanNotSeeTableRecords([$financiero2024])
        ->removeTableFilter('status')
        ->filterTable('fiscal_year', '2024')
        ->assertCanSeeTableRecords([$financiero2024])
        ->assertCanNotSeeTableRecords([$oficio2025])
        ->removeTableFilter('fiscal_year')
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$trashed2025])
        ->assertCanNotSeeTableRecords([$financiero2024, $oficio2025]);
});

test('quick row action publishes an internal record with documents and clears observations', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);

    $record = TransparencyRecord::factory()->interno()->withDocuments(1)->create([
        'observations' => 'Pendiente de revisión de datos personales.',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListTransparencyRecords::class)
        ->callAction(TestAction::make('toggle_visibility')->table($record))
        ->assertNotified('Registro marcado como Público');

    $record->refresh();

    expect($record->status)->toBe(TransparencyRecordStatus::Publico)
        ->and($record->observations)->toBeNull();
});

test('quick row action blocks publishing a record without documents and keeps status as interno', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);

    $record = TransparencyRecord::factory()->interno()->create([
        'observations' => 'Documento pendiente de escaneo.',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListTransparencyRecords::class)
        ->callAction(TestAction::make('toggle_visibility')->table($record))
        ->assertNotified('No se puede hacer público');

    $record->refresh();

    expect($record->status)->toBe(TransparencyRecordStatus::Interno)
        ->and($record->observations)->toBe('Documento pendiente de escaneo.');
});

test('quick row action unpublishes a public record back to consulta interna', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);

    $record = TransparencyRecord::factory()->publico()->withDocuments(1)->create([
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListTransparencyRecords::class)
        ->callAction(TestAction::make('toggle_visibility')->table($record))
        ->assertNotified('Registro marcado como Consulta Interna');

    expect($record->fresh()->status)->toBe(TransparencyRecordStatus::Interno);
});
