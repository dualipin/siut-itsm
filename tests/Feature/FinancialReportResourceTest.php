<?php

use App\Filament\Resources\FinancialReports\FinancialReportResource;
use App\Filament\Resources\FinancialReports\Pages\CreateFinancialReport;
use App\Filament\Resources\FinancialReports\Pages\ListFinancialReports;
use App\Filament\Resources\FinancialReports\Pages\ViewFinancialReport;
use App\Models\FinancialReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
});

test('it can render financial reports list page', function () {
    $user = User::factory()->create();
    $report = FinancialReport::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ListFinancialReports::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$report]);
});

test('it validates required fields on financial report creation', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateFinancialReport::class)
        ->fillForm([
            'year' => null,
            'file' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'year' => 'required',
            'file' => 'required',
        ]);
});

test('it only allows a single file in the financial_reports media collection', function () {
    Storage::fake('public');

    $report = FinancialReport::factory()->create();

    $report->addMediaFromString('primer archivo de prueba')
        ->setFileName('reporte_anterior.pdf')
        ->toMediaCollection('financial_reports');

    expect($report->getMedia('financial_reports'))->toHaveCount(1)
        ->and($report->getFirstMedia('financial_reports')->file_name)->toBe('reporte_anterior.pdf');

    // Adding another media item replaces the existing one due to singleFile()
    $report->addMediaFromString('segundo archivo de prueba')
        ->setFileName('reporte_nuevo.pdf')
        ->toMediaCollection('financial_reports');

    $report->refresh();

    expect($report->getMedia('financial_reports'))->toHaveCount(1)
        ->and($report->getFirstMedia('financial_reports')->file_name)->toBe('reporte_nuevo.pdf');
});

test('it displays the document in the financial report detail view', function () {
    Storage::fake('public');

    $user = User::factory()->create([
        'name' => 'Carlos',
        'surnames' => 'López',
    ]);
    $report = FinancialReport::factory()->create([
        'year' => 2025,
        'created_by' => $user->id,
    ]);

    $report->addMediaFromString('contenido del pdf financiero')
        ->setFileName('ejercicio-2025.pdf')
        ->toMediaCollection('financial_reports');

    $this->actingAs($user);

    Livewire::test(ViewFinancialReport::class, ['record' => $report->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('ejercicio-2025.pdf')
        ->assertSee('2025')
        ->assertSee('Carlos López');
});

test('it shows fallback when financial report has no document in detail view', function () {
    $user = User::factory()->create();
    $report = FinancialReport::factory()->create(['created_by' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ViewFinancialReport::class, ['record' => $report->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Sin documento');
});

test('guest cannot open the financial report document without an active session', function () {
    Storage::fake('public');

    $report = FinancialReport::factory()->create();
    $report->addMediaFromString('contenido confidencial')
        ->setFileName('reporte-privado.pdf')
        ->toMediaCollection('financial_reports');

    $response = $this->get(FinancialReportResource::getUrl('document', ['record' => $report]));

    $response->assertRedirect(route('filament.portal.auth.login'));
});

test('authenticated user can open and view the financial report document', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $report = FinancialReport::factory()->create();
    $report->addMediaFromString('contenido confidencial')
        ->setFileName('reporte-privado.pdf')
        ->toMediaCollection('financial_reports');

    $response = $this->actingAs($user)->get(FinancialReportResource::getUrl('document', ['record' => $report]));

    $response->assertSuccessful();
    $response->assertHeader('content-disposition', 'inline; filename="reporte-privado.pdf"');
});

test('authenticated user can download the financial report document with download parameter', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $report = FinancialReport::factory()->create();
    $report->addMediaFromString('contenido confidencial')
        ->setFileName('reporte-privado.pdf')
        ->toMediaCollection('financial_reports');

    $response = $this->actingAs($user)->get(FinancialReportResource::getUrl('document', ['record' => $report, 'download' => 1]));

    $response->assertSuccessful();
    $response->assertHeader('content-disposition', 'attachment; filename="reporte-privado.pdf"');
});

test('document route returns 404 if the financial report has no document', function () {
    $user = User::factory()->create();
    $report = FinancialReport::factory()->create();

    $response = $this->actingAs($user)->get(FinancialReportResource::getUrl('document', ['record' => $report]));

    $response->assertNotFound();
});
