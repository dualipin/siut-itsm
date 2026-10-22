<?php

use Illuminate\Support\Carbon;

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
    $firstFortnight = Carbon::now()->addDays(25)->setDay(15)->format('Y-m-d');
    // Ensure it doesn't exceed November 30
    $nov30 = Carbon::parse(now()->format('Y').'-11-30');
    if (Carbon::parse($firstFortnight)->isAfter($nov30)) {
        $this->markTestSkipped('Cannot run near year-end when Nov 30 boundary is breached.');
    }

    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 4,
            'first_fortnight' => $firstFortnight,
        ],
    ]);

    $response->assertOk();
    $response->assertJsonStructure(['pdf_url']);
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
    $firstFortnight = Carbon::now()->addDays(25)->setDay(15)->format('Y-m-d');
    $nov30 = Carbon::parse(now()->format('Y').'-11-30');
    if (Carbon::parse($firstFortnight)->isAfter($nov30)) {
        $this->markTestSkipped('Cannot run near year-end when Nov 30 boundary is breached.');
    }

    $response = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            // missing amount
            'fortnights' => 4,
            'first_fortnight' => $firstFortnight,
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['nomina.amount']);
});

it('GET download with valid token returns pdf response', function () {
    $firstFortnight = Carbon::now()->addDays(25)->setDay(15)->format('Y-m-d');
    $nov30 = Carbon::parse(now()->format('Y').'-11-30');
    if (Carbon::parse($firstFortnight)->isAfter($nov30)) {
        $this->markTestSkipped('Cannot run near year-end when Nov 30 boundary is breached.');
    }

    // First simulate to get token
    $simulateResponse = $this->postJson('/api/simulador-prestamo/simulate', [
        'monthly_rate' => 6,
        'payment_type' => 'nomina',
        'nomina' => [
            'amount' => 10000,
            'fortnights' => 4,
            'first_fortnight' => $firstFortnight,
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

it('GET download with invalid token returns 404', function () {
    $response = $this->get('/api/simulador-prestamo/download/non-existent-token');

    $response->assertNotFound();
});
