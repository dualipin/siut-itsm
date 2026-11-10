<?php

use App\Services\LoanSimulationService;

beforeEach(function () {
    $this->service = new LoanSimulationService;
});

it('builds a nomina amortization table with the correct number of rows', function () {
    $params = [
        'monthly_rate' => 0.06,
        'disbursement_date' => '2026-01-01',
        'payment_methods' => [
            'nomina' => [
                'amount' => 10000.0,
                'fortnights' => 4,
                'first_fortnight' => '2026-01-31',
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    // 4 fortnights + 1 extra-days row (Jan 1 → Jan 15 = 14 days)
    expect($table)->toHaveCount(5);
    expect($table[0]['type'])->toBe('extra_days');
    expect($table[0]['date'])->toBe('2026-01-15');

    // Fortnight rows start on first_fortnight (2026-01-31)
    $fortnightRows = array_values(array_filter($table, fn ($r) => $r['type'] === 'nomina'));
    expect($fortnightRows)->toHaveCount(4);
    expect($fortnightRows[0]['date'])->toBe('2026-01-31');
    expect($fortnightRows[3]['date'])->toBe('2026-03-15');
});

it('closing balance reaches zero after the last nomina fortnight', function () {
    $params = [
        'monthly_rate' => 0.06,
        'disbursement_date' => '2026-03-01',
        'payment_methods' => [
            'nomina' => [
                'amount' => 6000.0,
                'fortnights' => 6,
                'first_fortnight' => '2026-03-15',
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);
    $lastRow = end($table);

    expect($lastRow['closing_balance'])->toBe(0.0);
});

// ── Test A: correct fortnight row count for a benefit without nómina ────────
it('generates the correct number of fortnight rows for a benefit without nomina', function () {
    // Disbursement Jan 15, collection Mar 31: fortnights are Jan 15→Jan 31, Jan 31→Feb 15,
    // Feb 15→Feb 28, Feb 28→Mar 15, Mar 15→Mar 31 — 4 benefit_growth + 1 benefit = 5 rows
    $params = [
        'monthly_rate' => 0.06,
        'disbursement_date' => '2026-01-15',
        'payment_methods' => [
            'benefits' => [
                [
                    'benefit_id' => 1,
                    'name' => 'Aguinaldo',
                    'amount' => 5000.0,
                    'collection_date' => '2026-03-31',
                ],
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    $growthRows = array_values(array_filter($table, fn ($r) => $r['type'] === 'benefit_growth'));
    $benefitRows = array_values(array_filter($table, fn ($r) => $r['type'] === 'benefit'));

    expect($growthRows)->toHaveCount(4);
    expect($benefitRows)->toHaveCount(1);
    expect(end($table)['closing_balance'])->toBe(0.0);
    expect(end($table)['type'])->toBe('benefit');
});

// ── Test B: first fortnight uses the equivalent daily rate (1+r_m)^(1/30)−1 ─
it('uses the equivalent daily rate for benefit interest calculation', function () {
    $monthlyRate = 0.06;
    $dailyRate = pow(1 + $monthlyRate, 1 / 30) - 1;
    $amount = 5000.0;

    // Base Jan 15, next fortnight Jan 31 → 16 days
    $params = [
        'monthly_rate' => $monthlyRate,
        'disbursement_date' => '2026-01-15',
        'payment_methods' => [
            'benefits' => [
                [
                    'benefit_id' => 1,
                    'name' => 'Aguinaldo',
                    'amount' => $amount,
                    'collection_date' => '2026-03-31',
                ],
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);
    $firstRow = $table[0]; // first benefit_growth row

    // First period: Jan 15 → Jan 31 = 16 days
    $expectedInterest = round($amount * (pow(1 + $dailyRate, 16) - 1), 2);

    expect($firstRow['type'])->toBe('benefit_growth');
    expect($firstRow['interest'])->toBe($expectedInterest);
});

// ── Test C: D5 exception — last nómina ≤ 15 days before collection → use disbursement ─
it('uses disbursement date as base when last nomina is within 15 days of collection', function () {
    // nómina: 2 fortnights starting Jan 15, last date = Jan 31
    // collection: Feb 15 → diff = 15 days → rule D5 uses disbursement (Jan 1) instead
    $paramsWithException = [
        'monthly_rate' => 0.06,
        'disbursement_date' => '2026-01-01',
        'payment_methods' => [
            'nomina' => [
                'amount' => 4000.0,
                'fortnights' => 2,
                'first_fortnight' => '2026-01-15',
            ],
            'benefits' => [
                [
                    'benefit_id' => 1,
                    'name' => 'Prima vacacional',
                    'amount' => 3000.0,
                    'collection_date' => '2026-02-15',
                ],
            ],
        ],
    ];

    // Without exception: last nómina > 15 days before collection (same collection but earlier last nómina)
    $paramsNoException = [
        'monthly_rate' => 0.06,
        'disbursement_date' => '2026-01-01',
        'payment_methods' => [
            'nomina' => [
                'amount' => 4000.0,
                'fortnights' => 1,
                'first_fortnight' => '2026-01-15',
            ],
            'benefits' => [
                [
                    'benefit_id' => 1,
                    'name' => 'Prima vacacional',
                    'amount' => 3000.0,
                    'collection_date' => '2026-02-15',
                ],
            ],
        ],
    ];

    $tableException = $this->service->buildAmortizationTable($paramsWithException);
    $tableNoException = $this->service->buildAmortizationTable($paramsNoException);

    $benefitRowsException = array_filter($tableException, fn ($r) => in_array($r['type'], ['benefit_growth', 'benefit']));
    $benefitRowsNoException = array_filter($tableNoException, fn ($r) => in_array($r['type'], ['benefit_growth', 'benefit']));

    // When D5 exception applies (last nómina ≤ 15 days before collection), base is disbursement →
    // more rows (longer period) than when base is last nómina
    expect(count($benefitRowsException))->toBeGreaterThan(count($benefitRowsNoException));
});

it('last valid nomina date is November 30 (boundary test)', function () {
    $params = [
        'monthly_rate' => 0.075,
        'disbursement_date' => '2026-11-01',
        'payment_methods' => [
            'nomina' => [
                'amount' => 4000.0,
                'fortnights' => 2,
                'first_fortnight' => '2026-11-15',
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    // Disbursement is Nov 1 (within Oct 31 → Nov 15), so no extra_days row
    expect($table)->toHaveCount(2);

    $fortnightRows = array_values(array_filter($table, fn ($r) => $r['type'] === 'nomina'));
    expect($fortnightRows[0]['date'])->toBe('2026-11-15');
    expect($fortnightRows[1]['date'])->toBe('2026-11-30');
});

it('builds a correct financial run summary', function () {
    $params = [
        'monthly_rate' => 0.06,
        'disbursement_date' => '2026-01-01',
        'payment_methods' => [
            'nomina' => [
                'amount' => 10000.0,
                'fortnights' => 4,
                'first_fortnight' => '2026-01-15',
            ],
        ],
    ];

    $summary = $this->service->buildFinancialRunSummary($params);

    expect($summary['amount'])->toBe(10000.0);
    expect($summary['monthly_rate_percent'])->toBe('6.00 %');
    expect($summary['fortnights'])->toBe(4);
    expect($summary['total_payment'])->toBeGreaterThan(10000.0); // interest added
    expect($summary['total_principal'])->toBe(10000.0);
});

it('uses real calendar days for February fortnight (15 Feb → 28 Feb = 13 days)', function () {
    // 2026 is not a leap year, so Feb has 28 days
    $params = [
        'monthly_rate' => 0.06,
        'disbursement_date' => '2026-01-31',
        'payment_methods' => [
            'nomina' => [
                'amount' => 10000.0,
                'fortnights' => 2,
                'first_fortnight' => '2026-02-28',
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    // Rows: extra_days (Jan 31 → Feb 15 = 15 days), Quincena 1 (Feb 15 → Feb 28, 13 days), Quincena 2 (Feb 28 → Mar 15, 15 days)
    $fortnightRows = array_values(array_filter($table, fn ($r) => $r['type'] === 'nomina'));

    expect($fortnightRows)->toHaveCount(2);

    // First fortnight: Feb 15 → Feb 28 = 13 days of interest
    $dailyRate = 0.06 / 30;
    $expectedInterestFirst = round(10000.0 * $dailyRate * 13, 2);

    expect($fortnightRows[0]['date'])->toBe('2026-02-28');
    expect($fortnightRows[0]['interest'])->toBe($expectedInterestFirst);
});

it('uses 16 real days for the period Jan 15 → Jan 31', function () {
    // Jan 15 → Jan 31 = 16 days
    $params = [
        'monthly_rate' => 0.06,
        'disbursement_date' => '2026-01-01',
        'payment_methods' => [
            'nomina' => [
                'amount' => 6000.0,
                'fortnights' => 2,
                'first_fortnight' => '2026-01-31',
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    $fortnightRows = array_values(array_filter($table, fn ($r) => $r['type'] === 'nomina'));

    expect($fortnightRows)->toHaveCount(2);

    // First fortnight: Jan 15 → Jan 31 = 16 days of interest
    $dailyRate = 0.06 / 30;
    $expectedInterestFirst = round(6000.0 * $dailyRate * 16, 2);

    expect($fortnightRows[0]['date'])->toBe('2026-01-31');
    expect($fortnightRows[0]['interest'])->toBe($expectedInterestFirst);
});

it('aligns extra_days cutoff and fortnight dates without shifting December fortnights into January', function () {
    $params = [
        'monthly_rate' => 0.06,
        'disbursement_date' => '2026-11-10',
        'payment_methods' => [
            'nomina' => [
                'amount' => 10000.0,
                'fortnights' => 2,
                'first_fortnight' => '2026-12-15',
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);
    $summary = $this->service->buildFinancialRunSummary($params);

    expect($table)->toHaveCount(3);

    // Row 0: extra_days from 2026-11-10 to 2026-11-30 (20 days)
    expect($table[0]['type'])->toBe('extra_days')
        ->and($table[0]['date'])->toBe('2026-11-30')
        ->and($table[0]['interest'])->toBe(400.0);

    // Row 1: Quincena 1 on 2026-12-15 (Nov 30 → Dec 15 = 15 days)
    expect($table[1]['type'])->toBe('nomina')
        ->and($table[1]['label'])->toBe('Quincena 1')
        ->and($table[1]['date'])->toBe('2026-12-15')
        ->and($table[1]['interest'])->toBe(300.0);

    // Row 2: Quincena 2 on 2026-12-31 (Dec 15 → Dec 31 = 16 days)
    expect($table[2]['type'])->toBe('nomina')
        ->and($table[2]['label'])->toBe('Quincena 2')
        ->and($table[2]['date'])->toBe('2026-12-31')
        ->and($table[2]['interest'])->toBe(160.0);

    // Summary last_fortnight matches last table row date (never spills into January)
    expect($summary['first_fortnight'])->toBe('2026-12-15')
        ->and($summary['last_fortnight'])->toBe('2026-12-31')
        ->and($table[2]['date'])->toBe($summary['last_fortnight']);
});
