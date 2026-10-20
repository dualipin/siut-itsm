<?php

use App\Services\LoanSimulationService;
use Illuminate\Support\Carbon;

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
                'first_fortnight' => '2026-01-15',
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    // 4 fortnights + 1 extra-days row (Jan 1 → Jan 15 = 14 days)
    expect($table)->toHaveCount(5);
    expect($table[0]['type'])->toBe('extra_days');
    expect($table[0]['date'])->toBe('2026-01-15');

    // Fortnight rows
    $fortnightRows = array_filter($table, fn ($r) => $r['type'] === 'nomina');
    expect($fortnightRows)->toHaveCount(4);
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

it('calculates compound interest for a benefit payment', function () {
    $disbursementDate = '2026-01-01';
    $collectionDate = '2026-12-20';
    $benefitAmount = 5000.0;
    $monthlyRate = 0.06;

    $params = [
        'monthly_rate' => $monthlyRate,
        'disbursement_date' => $disbursementDate,
        'payment_methods' => [
            'benefits' => [
                [
                    'benefit_id' => 1,
                    'amount' => $benefitAmount,
                    'collection_date' => $collectionDate,
                ],
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    expect($table)->toHaveCount(1);
    $row = $table[0];

    $days = Carbon::parse($disbursementDate)->diffInDays(Carbon::parse($collectionDate));
    $expectedInterest = round($benefitAmount * (pow(1 + $monthlyRate / 30, $days) - 1), 2);

    expect($row['type'])->toBe('benefit');
    expect($row['interest'])->toBe($expectedInterest);
    expect($row['closing_balance'])->toBe(0.0);
});

it('last valid nomina date is November 30 (boundary test)', function () {
    $params = [
        'monthly_rate' => 0.075,
        'disbursement_date' => '2026-11-01',
        'payment_methods' => [
            'nomina' => [
                'amount' => 4000.0,
                'fortnights' => 2,
                'first_fortnight' => '2026-11-15',  // Nov 15 is valid
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    // extra days row + 2 fortnight rows
    expect($table)->toHaveCount(3);

    $lastRow = end($table);
    // Second fortnight should land on Nov 30
    expect($lastRow['date'])->toBe('2026-11-30');
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
                'first_fortnight' => '2026-02-15',
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    // Rows: extra_days (Jan 31 → Feb 15 = 15 days), Quincena 1 (Feb 15, 15 days), Quincena 2 (Feb 28, 13 days)
    $fortnightRows = array_values(array_filter($table, fn ($r) => $r['type'] === 'nomina'));

    expect($fortnightRows)->toHaveCount(2);

    // Second fortnight: Feb 15 → Feb 28 = 13 days of interest
    $dailyRate = 0.06 / 30;
    $principalPerFortnight = round(10000.0 / 2, 2);
    $balanceBeforeSecond = max(0.0, round(10000.0 - $principalPerFortnight, 2));
    $expectedInterestSecond = round($balanceBeforeSecond * $dailyRate * 13, 2);

    expect($fortnightRows[1]['date'])->toBe('2026-02-28');
    expect($fortnightRows[1]['interest'])->toBe($expectedInterestSecond);
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
                'first_fortnight' => '2026-01-15',
            ],
        ],
    ];

    $table = $this->service->buildAmortizationTable($params);

    $fortnightRows = array_values(array_filter($table, fn ($r) => $r['type'] === 'nomina'));

    expect($fortnightRows)->toHaveCount(2);

    // Second fortnight: Jan 15 → Jan 31 = 16 days of interest
    $dailyRate = 0.06 / 30;
    $principalPerFortnight = round(6000.0 / 2, 2);
    $balanceBeforeSecond = max(0.0, round(6000.0 - $principalPerFortnight, 2));
    $expectedInterestSecond = round($balanceBeforeSecond * $dailyRate * 16, 2);

    expect($fortnightRows[1]['date'])->toBe('2026-01-31');
    expect($fortnightRows[1]['interest'])->toBe($expectedInterestSecond);
});
