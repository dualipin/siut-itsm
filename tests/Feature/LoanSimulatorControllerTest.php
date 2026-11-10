<?php

use App\Models\Benefit;
use App\Services\LoanCalendarService;
use Illuminate\Support\Carbon;

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

    $response->assertOk();
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

it('POST simulate rejects missing nomina.first_fortnight with only required error and rejects nomina simulation after december 1', function () {
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

    Carbon::setTestNow('2026-12-01');

    $closedResponse = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 5000,
            'fortnights' => 1,
            'first_fortnight' => '2026-12-31',
        ],
    ]);

    $closedResponse->assertStatus(422);
    $closedResponse->assertJsonValidationErrors(['payment_type', 'nomina.first_fortnight']);
});

it('POST simulate rejects any simulation when the application window has closed on or after december 1', function () {
    Carbon::setTestNow('2026-12-01');

    $benefit = Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/simulador-prestamo/simulate', [
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

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['payment_type']);
});

it('GET download with invalid token returns 404', function () {
    $response = $this->get('/api/simulador-prestamo/download/non-existent-token');

    $response->assertNotFound();
});
