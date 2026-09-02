<?php

use App\Models\FinancialReport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('guest can visit the public financial reports page and see reports', function () {
    $author = User::factory()->create();

    FinancialReport::factory()->create([
        'year' => 2024,
        'created_by' => $author->id,
    ]);

    FinancialReport::factory()->create([
        'year' => 2025,
        'created_by' => $author->id,
    ]);

    $response = $this->get(route('financial-reports.index'));

    $response->assertSuccessful();
    $response->assertSee('Informes Financieros');
    $response->assertSee('2024');
    $response->assertSee('2025');
    $response->assertSee('Transparencia');
});

test('guest can filter financial reports by year parameter', function () {
    $author = User::factory()->create();

    FinancialReport::factory()->create([
        'year' => 2023,
        'created_by' => $author->id,
    ]);

    FinancialReport::factory()->create([
        'year' => 2025,
        'created_by' => $author->id,
    ]);

    $response = $this->get(route('financial-reports.index', ['year' => 2025]));

    $response->assertSuccessful();
    $response->assertSee('2025');
    $response->assertSee('Limpiar filtro');
});

test('guest can search financial reports using search input with year', function () {
    $author = User::factory()->create();

    FinancialReport::factory()->create([
        'year' => 2022,
        'created_by' => $author->id,
    ]);

    FinancialReport::factory()->create([
        'year' => 2024,
        'created_by' => $author->id,
    ]);

    $response = $this->get(route('financial-reports.index', ['search' => '2024']));

    $response->assertSuccessful();
    $response->assertSee('2024');
});

test('public page does not expose document download link and displays restricted access notice to guests', function () {
    $author = User::factory()->create();
    $report = FinancialReport::factory()->create([
        'year' => 2025,
        'created_by' => $author->id,
    ]);

    $report->addMediaFromString('Contenido de prueba confidencial')
        ->setFileName('balance_general_2025.pdf')
        ->toMediaCollection('financial_reports');

    $response = $this->get(route('financial-reports.index'));

    $response->assertSuccessful();
    // Confirms no media link or direct download link is visible on public page
    $response->assertDontSee('balance_general_2025.pdf');
    $response->assertSee('Documento protegido');
    $response->assertSee('Iniciar sesión para ver');
    $response->assertSee(route('filament.portal.auth.login'));
});

test('authenticated user sees link to access reports in the portal', function () {
    $user = User::factory()->create();
    FinancialReport::factory()->create([
        'year' => 2025,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('financial-reports.index'));

    $response->assertSuccessful();
    $response->assertSee('Ver en portal');
    $response->assertSee(route('filament.portal.resources.financial-reports.index'));
});

test('legacy sindicato transparencia informes financieros url redirects to public page', function () {
    $response = $this->get('/sindicato/transparencia/informes-financieros');

    $response->assertRedirect('/transparencia/informes-financieros');
});

test('empty state is displayed when no financial reports match search', function () {
    $author = User::factory()->create();
    FinancialReport::factory()->create([
        'year' => 2025,
        'created_by' => $author->id,
    ]);

    $response = $this->get(route('financial-reports.index', ['year' => 1999]));

    $response->assertSuccessful();
    $response->assertSee('No se encontraron informes');
    $response->assertSee('1999');
});
