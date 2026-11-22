<?php

use App\Enums\LoanOperationalState;
use App\Models\Benefit;
use App\Models\User;
use App\Services\LoanCalendarService;
use App\Services\LoanOperationalStateService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Carbon::setTestNow('2026-06-01');
});

afterEach(function () {
    Carbon::setTestNow();
});

function firstEligibleFortnightDate(): string
{
    return app(LoanCalendarService::class)->getFirstEligibleFortnight()->format('Y-m-d');
}

it('renders the combined simulation PDF view with its financial sections', function () {
    $html = view('pdf.loan-simulation-combined', [
        'primaryColor' => '#611232',
        'summary' => [
            'amount' => 10000,
            'monthly_rate_percent' => '6%',
            'disbursement_date' => '2026-01-01',
            'fortnights' => 4,
            'first_fortnight' => '2026-01-15',
            'last_fortnight' => '2026-02-28',
            'total_principal' => 10000,
            'total_interest' => 250,
            'total_payment' => 10250,
        ],
        'amortizationTable' => [[
            'type' => 'regular',
            'period' => 1,
            'label' => 'Quincena 1',
            'date' => '2026-01-15',
            'opening_balance' => 10000,
            'principal' => 2500,
            'interest' => 100,
            'payment' => 2600,
            'closing_balance' => 7500,
        ]],
    ])->render();

    expect($html)
        ->toContain('Corrida Financiera')
        ->toContain('Tabla de Amortización')
        ->toContain('Documento informativo sin validez legal')
        ->toContain('Quincena 1')
        ->toContain('$ 10,250.00');
});

it('GET /simulador-prestamo returns 200', function () {
    $response = $this->get('/simulador-prestamo');

    $response->assertOk()
        ->assertViewHas('suspensionMessage', LoanOperationalStateService::DEFAULT_SUSPENSION_MESSAGE);
});

it('POST simulate with valid nomina data returns json with pdf_url', function () {
    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 4,
            'first_fortnight' => firstEligibleFortnightDate(),
        ],
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['pdf_url', 'application_url']);
});

it('POST simulate with invalid data returns 422', function () {
    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 5,   // not in [6, 7.5, 8, 9.5]
        'payment_type' => 'nomina',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['monthly_rate']);
});

it('POST simulate with benefit payment type requires benefits array', function () {
    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6.0,
        'payment_type' => 'benefit',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['benefits']);
});

it('POST simulate nomina requires nomina.amount', function () {
    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            // missing amount
            'fortnights' => 4,
            'first_fortnight' => firstEligibleFortnightDate(),
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['nomina.amount']);
});

it('GET download with valid token returns pdf response', function () {
    // First simulate to get token
    $simulateResponse = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 4,
            'first_fortnight' => firstEligibleFortnightDate(),
        ],
    ]);

    $simulateResponse->assertOk();
    $pdfUrl = $simulateResponse->json('pdf_url');

    // Extract token from URL and fetch the PDF
    $token = basename($pdfUrl);
    $downloadResponse = $this->get("/api/simulador-prestamo/download/{$token}");

    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('Content-Type', 'application/pdf');

    // Filename must include the first 8 chars of the token
    $tokenPrefix = substr($token, 0, 8);
    $downloadResponse->assertHeader('Content-Disposition', "attachment; filename=simulacion-prestamo-{$tokenPrefix}.pdf");

    // No-cache directives must be present (DomPDF merges its own Cache-Control values)
    expect($downloadResponse->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('no-cache');
    $downloadResponse->assertHeader('Pragma', 'no-cache');
});

it('POST simulate allows first fortnight in december up to december 31', function () {
    Carbon::setTestNow('2026-11-15');

    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 5000,
            'fortnights' => 2,
            'first_fortnight' => '2026-12-15',
        ],
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['pdf_url', 'application_url']);
});

it('POST simulate rejects first fortnight after december 31', function () {
    Carbon::setTestNow('2026-11-15');

    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 5000,
            'fortnights' => 1,
            'first_fortnight' => '2027-01-15',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['nomina.first_fortnight']);
    expect($response->json()['errors']['nomina.first_fortnight'][0])
        ->toBe('La última fecha válida para nómina es el 31 de diciembre del año en curso.');
});

it('POST simulate rejects nomina fortnights when calculated last fortnight exceeds december 31', function () {
    Carbon::setTestNow('2026-11-15');

    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 5000,
            'fortnights' => 3,
            'first_fortnight' => '2026-12-15',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['nomina.fortnights']);
});

it('POST simulate rejects a first fortnight different from the automatic first eligible fortnight', function () {
    Carbon::setTestNow('2026-11-10');

    // On 2026-11-10 (+20 days = 2026-11-30), first eligible is 2026-12-15; 2026-12-31 must be rejected
    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 5000,
            'fortnights' => 1,
            'first_fortnight' => '2026-12-31',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['nomina.first_fortnight']);
});

it('POST simulate rejects missing nomina.first_fortnight with only required error', function () {
    $missingResponse = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 5000,
            'fortnights' => 1,
        ],
    ]);

    $missingResponse->assertStatus(422);
    expect($missingResponse->json('errors')['nomina.first_fortnight'])->toHaveCount(1);
});

it('allows nomina and benefit simulation in december while Activo when eligible dates exist', function () {
    Carbon::setTestNow('2026-12-05');

    $benefit = Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    $this->get('/simulador-prestamo')
        ->assertOk()
        ->assertDontSee('No hay fechas disponibles');

    // Nomina simulation on Dec 5 uses Dec 31 as first and only eligible fortnight
    $nominaResponse = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 5000,
            'fortnights' => 1,
            'first_fortnight' => '2026-12-31',
        ],
    ]);

    $nominaResponse->assertOk()
        ->assertJsonStructure(['pdf_url', 'application_url']);

    // Benefit simulation on Dec 15 (no fortnights left, but Dec 20 benefit is still future)
    Carbon::setTestNow('2026-12-15');

    $benefitResponse = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'benefit',
        'benefits' => [
            [
                'benefit_id' => $benefit->id,
                'amount' => 5000,
                'collection_date' => '2026-12-20',
            ],
        ],
    ]);

    $benefitResponse->assertOk()
        ->assertJsonStructure(['pdf_url', 'application_url']);
});

it('informs clearly in simulator page and rejects simulation in december while Activo when no eligible fortnights or benefits remain', function () {
    Carbon::setTestNow('2026-12-15');

    // Past benefit in same year should not count as available
    Benefit::factory()->create([
        'name' => 'Prima Vacacional',
        'collection_month' => 7,
        'collection_day' => 15,
        'is_active' => true,
    ]);

    $this->get('/simulador-prestamo')
        ->assertOk()
        ->assertSee('No hay fechas disponibles')
        ->assertSee('Cierre de año')
        ->assertDontSee('30 de noviembre');

    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 5000,
            'fortnights' => 1,
            'first_fortnight' => '2026-12-31',
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payment_type']);

    expect($response->json('errors.payment_type.0'))
        ->toContain('No hay fechas disponibles')
        ->toContain('Cierre de año')
        ->not->toContain('30 de noviembre');
});

it('GET download with invalid token returns 404', function () {
    $response = $this->get('/api/simulador-prestamo/download/non-existent-token');

    $response->assertNotFound();
});

it('shows suspension notice and blocks simulate and download when operational state is temporarily out of service', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $stateService = app(LoanOperationalStateService::class);

    // Simulate while Activo to obtain a valid download token
    $activeSimulate = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 4,
            'first_fortnight' => firstEligibleFortnightDate(),
        ],
    ]);
    $activeSimulate->assertOk();
    $token = basename($activeSimulate->json('pdf_url'));

    // Switch to TemporalmenteFueraDeServicio with custom message
    $customMessage = 'Servicio suspendido por cierre de auditoría trimestral.';
    $stateService->updateState($admin, LoanOperationalState::TemporalmenteFueraDeServicio, $customMessage);

    // 1. Public simulator page shows custom suspension notice
    $this->get('/simulador-prestamo')
        ->assertOk()
        ->assertSee($customMessage)
        ->assertSee('Servicio temporalmente fuera de servicio');

    // 2. Simulate endpoint rejects with 422 and suspension message
    $blockedSimulate = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 4,
            'first_fortnight' => '2026-06-30',
        ],
    ]);
    $blockedSimulate->assertStatus(422)
        ->assertJsonValidationErrors(['payment_type'])
        ->assertJsonPath('errors.payment_type.0', $customMessage);

    // 3. Download endpoint blocks even an existing valid token with 503
    $this->get("/api/simulador-prestamo/download/{$token}")
        ->assertStatus(503);

    // 4. Empty suspension message falls back to default institutional message
    $stateService->updateState($admin, LoanOperationalState::TemporalmenteFueraDeServicio, null);

    $this->get('/simulador-prestamo')
        ->assertOk()
        ->assertSee(LoanOperationalStateService::DEFAULT_SUSPENSION_MESSAGE);

    $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 4,
            'first_fortnight' => '2026-06-30',
        ],
    ])->assertStatus(422)
        ->assertJsonPath('errors.payment_type.0', LoanOperationalStateService::DEFAULT_SUSPENSION_MESSAGE);

    // 5. Restoring to Activo re-enables simulation and download
    $stateService->updateState($admin, LoanOperationalState::Activo);

    $this->get("/api/simulador-prestamo/download/{$token}")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('shows Cierre de año banner with projected 2027 benefits, calculates interest from Jan 1, enforces Dec 28 tolerance, and renders fiscal year in PDF', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $stateService = app(LoanOperationalStateService::class);

    $aguinaldo = Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    // 1. November 15, 2026 in Cierre de año (target 2027)
    Carbon::setTestNow('2026-11-15 10:00:00');
    $stateService->updateState($admin, LoanOperationalState::CierreAnio);

    $page = $this->get('/simulador-prestamo');
    $page->assertOk()
        ->assertSee('Cierre de año')
        ->assertSee('2026')
        ->assertSee('2027')
        ->assertSee('2027-12-20')
        ->assertDontSee('2026-12-20');

    // Rejects 2026 fortnight and 2026 benefit collection date
    $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 1,
            'first_fortnight' => '2026-12-15',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['nomina.first_fortnight']);

    $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'benefit',
        'benefits' => [[
            'benefit_id' => $aguinaldo->id,
            'amount' => 5000,
            'collection_date' => '2026-12-20',
        ]],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['benefits.0.collection_date']);

    $futureYearResponse = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'benefit',
        'benefits' => [[
            'benefit_id' => $aguinaldo->id,
            'amount' => 5000,
            'collection_date' => '2028-12-20',
        ]],
    ]);
    $futureYearResponse->assertStatus(422)
        ->assertJsonValidationErrors(['benefits.0.collection_date']);
    expect($futureYearResponse->json('errors')['benefits.0.collection_date'][0])
        ->toBe('La fecha de cobro de la prestación debe corresponder al ejercicio fiscal vigente.');

    // Accepts 2027-01-15 with 24 fortnights (Jan 15 .. Dec 31) and calculates from 2027-01-01
    $novSimulate = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 2,
            'first_fortnight' => '2027-01-15',
        ],
    ]);
    $novSimulate->assertOk();

    $tokenNov = basename($novSimulate->json('pdf_url'));
    $cachedSimNov = Cache::get('loan_simulation_'.$tokenNov);
    $cachedAppNov = Cache::get('loan_application_'.$tokenNov);

    expect($cachedSimNov['summary']['disbursement_date'])->toBe('2027-01-01')
        ->and($cachedSimNov['summary']['fiscal_year'])->toBe(2027)
        ->and($cachedAppNov['fiscal_year'])->toBe(2027)
        // 10000 * (0.06 / 30) * 14 days (2027-01-01 to 2027-01-15) = 280.00
        ->and($cachedSimNov['amortizationTable'][0]['interest'])->toBe(280.0);

    // Benefit simulation in Cierre de año accepts 2027-12-20 and computes from 2027-01-01
    $benefitSim = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'benefit',
        'benefits' => [[
            'benefit_id' => $aguinaldo->id,
            'amount' => 5000,
            'collection_date' => '2027-12-20',
        ]],
    ]);
    $benefitSim->assertOk();
    $benefitToken = basename($benefitSim->json('pdf_url'));
    $cachedBenefitSim = Cache::get('loan_simulation_'.$benefitToken);
    expect($cachedBenefitSim['summary']['disbursement_date'])->toBe('2027-01-01')
        ->and($cachedBenefitSim['summary']['fiscal_year'])->toBe(2027);

    // 2. December 28, 2026 edge case (+20 days = 2027-01-17) -> skips 2027-01-15, requires 2027-01-31
    Carbon::setTestNow('2026-12-28 10:00:00');

    $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 1,
            'first_fortnight' => '2027-01-15',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['nomina.first_fortnight']);

    $dec28Simulate = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 1,
            'first_fortnight' => '2027-01-31',
        ],
    ]);
    $dec28Simulate->assertOk();

    $tokenDec28 = basename($dec28Simulate->json('pdf_url'));
    $cachedSimDec28 = Cache::get('loan_simulation_'.$tokenDec28);

    expect($cachedSimDec28['summary']['disbursement_date'])->toBe('2027-01-01')
        ->and($cachedSimDec28['summary']['fiscal_year'])->toBe(2027)
        // Extra days from 2027-01-01 to 2027-01-15 = 14 days (280.00), NOT from 2026-12-28 (18 days)
        ->and($cachedSimDec28['amortizationTable'][0]['type'])->toBe('extra_days')
        ->and($cachedSimDec28['amortizationTable'][0]['interest'])->toBe(280.0);

    $pdfHtml = view('pdf.loan-simulation-combined', [
        'summary' => $cachedSimDec28['summary'],
        'amortizationTable' => $cachedSimDec28['amortizationTable'],
    ])->render();

    expect($pdfHtml)
        ->toContain('Año fiscal')
        ->toContain('2027')
        ->toContain('01/01/2027');

    $this->get("/api/simulador-prestamo/download/{$tokenDec28}")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // 3. January 1, 2027 auto-transition -> Activo for 2027, banner hidden, simulation uses 2027
    Carbon::setTestNow('2027-01-01 00:00:05');

    $jan1Page = $this->get('/simulador-prestamo');
    $jan1Page->assertOk()
        ->assertDontSee('Recepción anticipada para el ejercicio fiscal 2027')
        ->assertSee('"operationalState":"activo"', false)
        ->assertSee('"targetFiscalYear":2027', false);

    $jan1Sim = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 1,
            'first_fortnight' => '2027-01-31',
        ],
    ]);
    $jan1Sim->assertOk();
    $jan1Token = basename($jan1Sim->json('pdf_url'));
    expect(Cache::get('loan_application_'.$jan1Token)['fiscal_year'])->toBe(2027);
});

it('POST simulate accepts 10 percent monthly_rate for directivos no gremiales', function () {
    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 10,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 4,
            'first_fortnight' => firstEligibleFortnightDate(),
        ],
    ]);

    $response->assertOk()
        ->assertJsonStructure(['pdf_url', 'application_url']);

    $token = basename($response->json('pdf_url'));
    expect(Cache::get('loan_simulation_'.$token)['summary']['monthly_rate_percent'])->toBe('10.00 %');
});
