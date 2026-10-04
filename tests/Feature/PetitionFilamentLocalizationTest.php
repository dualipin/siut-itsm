<?php

use App\Enums\UserRole;
use App\Filament\Resources\AnnualPetitions\AnnualPetitionResource;
use App\Filament\Resources\AnnualPetitions\Pages\CreateAnnualPetition;
use App\Filament\Resources\AnnualPetitions\Pages\EditAnnualPetition;
use App\Filament\Resources\AnnualPetitions\Pages\ListAnnualPetitions;
use App\Filament\Resources\AnnualPetitions\Schemas\AnnualPetitionForm;
use App\Filament\Resources\PetitionRequests\Pages\ListPetitionRequests;
use App\Filament\Resources\PetitionRequests\Pages\ViewPetitionRequest;
use App\Filament\Resources\PetitionRequests\PetitionRequestResource;
use App\Models\AnnualPetition;
use App\Models\PetitionRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

test('petition resources expose spanish labels', function () {
    expect(AnnualPetitionResource::getModelLabel())->toBe('Pliego Anual')
        ->and(AnnualPetitionResource::getPluralModelLabel())->toBe('pliegos anuales')
        ->and(AnnualPetitionResource::getNavigationLabel())->toBe('Pliegos Anuales')
        ->and(AnnualPetitionResource::getBreadcrumb())->toBe('Pliegos Anuales');

    expect(PetitionRequestResource::getModelLabel())->toBe('Petición de Pliego')
        ->and(PetitionRequestResource::getPluralModelLabel())->toBe('peticiones de pliego')
        ->and(PetitionRequestResource::shouldRegisterNavigation())->toBeFalse()
        ->and(PetitionRequestResource::getBreadcrumb())->toBe('Peticiones de Pliego');
});

test('petition pages have spanish titles', function () {
    $title = fn (string $class): ?string => (new ReflectionClass($class))->getStaticPropertyValue('title');

    expect($title(ListAnnualPetitions::class))->toBe('Pliegos Anuales')
        ->and($title(CreateAnnualPetition::class))->toBe('Nuevo Pliego Anual')
        ->and($title(EditAnnualPetition::class))->toBe('Editar Pliego Anual')
        ->and($title(ListPetitionRequests::class))->toBe('Peticiones de Pliego')
        ->and($title(ViewPetitionRequest::class))->toBe('Detalle de la Petición');
});

test('every annual petition form field carries a spanish label', function () {
    $schema = AnnualPetitionForm::configure(
        Schema::make(Livewire::test(ListAnnualPetitions::class)->instance())
    );

    $components = collect($schema->getComponents(withHidden: true))->flatten();

    $labels = $components
        ->map(fn ($component) => method_exists($component, 'getLabel') ? $component->getLabel() : null)
        ->filter()
        ->values()
        ->all();

    expect($labels)
        ->toContain('Año', 'Fecha Límite', 'Convocatoria y Formato Base')
        ->each->toBeString();
});

test('annual petition table is labelled in spanish and offers the consolidated report', function () {
    $livewire = Livewire::test(ListAnnualPetitions::class)->instance();

    $columns = collect($livewire->getTable()->getColumns())
        ->map(fn ($column) => $column->getLabel())
        ->all();

    expect($columns)
        ->toContain('Año', 'Fecha Límite', 'Peticiones Recibidas', 'Fecha de Creación', 'Fecha de Actualización')
        ->each->toBeString();

    $action = collect($livewire->getTable()->getRecordActions())
        ->first(fn (Action $action) => $action->getName() === 'download_report');

    expect($action)->not->toBeNull()
        ->and($action->getLabel())->toBe('Descargar Reporte Consolidado');
});

test('an admin can list annual petitions and download the consolidated report', function () {
    $annualPetition = AnnualPetition::factory()->create(['year' => 2031, 'deadline' => '2031-03-31']);
    PetitionRequest::factory()->create([
        'annual_petition_id' => $annualPetition->id,
        'proposal' => 'Solicito mesa de diálogo.',
    ]);

    Livewire::test(ListAnnualPetitions::class)
        ->assertCanSeeTableRecords([$annualPetition])
        ->assertTableColumnExists('petition_requests_count')
        ->assertTableActionExists('download_report')
        ->callTableAction('download_report', $annualPetition);

    expect($annualPetition->refresh()->deadline->format('d/m/Y'))->toBe('31/03/2031');
});

test('petition requests are read only in the admin panel', function () {
    $petitionRequest = PetitionRequest::factory()->create();

    expect(PetitionRequestResource::canCreate())->toBeFalse()
        ->and(PetitionRequestResource::canDelete($petitionRequest))->toBeFalse()
        ->and(PetitionRequestResource::canEdit($petitionRequest))->toBeFalse();

    $livewire = Livewire::test(ListPetitionRequests::class)->instance();

    expect($livewire->getTable()->getBulkActions())->toBeEmpty();

    Livewire::test(ListPetitionRequests::class)
        ->assertCanSeeTableRecords([$petitionRequest])
        ->assertTableColumnExists('curp')
        ->assertTableColumnExists('proposal')
        ->assertTableColumnExists('media_count')
        ->assertTableActionExists('view');

    Livewire::test(ViewPetitionRequest::class, ['record' => $petitionRequest->getKey()])
        ->assertOk();
});

test('petition requests expose no create, edit or delete routes', function () {
    $names = collect(app(Router::class)->getRoutes()->getRoutes())
        ->map(fn (Route $route) => $route->getName())
        ->filter()
        ->values();

    expect($names->filter(fn (string $name) => str_contains($name, 'petition-requests'))->values()->all())
        ->toEqual([
            'filament.portal.resources.petition-requests.index',
            'filament.portal.resources.petition-requests.view',
        ]);
});

test('petition resources are registered in a filament panel', function () {
    $names = collect(app(Router::class)->getRoutes()->getRoutes())
        ->map(fn (Route $route) => $route->getName())
        ->filter()
        ->values();

    expect($names)
        ->toContain('filament.portal.resources.annual-petitions.index')
        ->toContain('filament.portal.resources.petition-requests.index')
        ->toContain('filament.portal.resources.petition-requests.view');
});

test('petition validation attributes resolve in spanish', function () {
    expect(__('validation.attributes.proposals'))->toBe('propuestas')
        ->and(__('validation.attributes.curp'))->toBe('CURP');

    expect(__('validation.required', ['attribute' => __('validation.attributes.proposals')]))
        ->toBe('El campo propuestas es obligatorio.');
});
