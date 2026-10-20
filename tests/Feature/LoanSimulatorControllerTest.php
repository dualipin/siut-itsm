<?php

use Illuminate\Support\Carbon;

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
});

it('GET download with invalid token returns 404', function () {
    $response = $this->get('/api/simulador-prestamo/download/non-existent-token');

    $response->assertNotFound();
});
