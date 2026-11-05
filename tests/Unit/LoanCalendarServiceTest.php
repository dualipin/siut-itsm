<?php

use App\Models\Benefit;
use App\Services\LoanCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->calendar = new LoanCalendarService;
});

afterEach(function () {
    Carbon::setTestNow();
});

it('permits applications on or before November 30 and blocks from December 1', function () {
    Carbon::setTestNow('2026-11-30 23:59:59');
    expect($this->calendar->isApplicationWindowOpen())->toBeTrue();

    Carbon::setTestNow('2026-12-01 00:00:00');
    expect($this->calendar->isApplicationWindowOpen())->toBeFalse();

    Carbon::setTestNow('2026-12-15 12:00:00');
    expect($this->calendar->isApplicationWindowOpen())->toBeFalse();
});

it('calculates the first eligible fortnight respecting the 20-day tolerance exactly as specified', function () {
    // 10/10/2026 + 20 days = 30/10/2026 -> first eligible is 31/10/2026
    Carbon::setTestNow('2026-10-10');
    $first1 = $this->calendar->getFirstEligibleFortnight();
    expect($first1?->format('Y-m-d'))->toBe('2026-10-31');

    // 11/10/2026 + 20 days = 31/10/2026 -> first eligible is 15/11/2026
    Carbon::setTestNow('2026-10-11');
    $first2 = $this->calendar->getFirstEligibleFortnight();
    expect($first2?->format('Y-m-d'))->toBe('2026-11-15');
});

it('does not return any fortnights after November 30', function () {
    Carbon::setTestNow('2026-11-15');
    // 15/11 + 20 days = 05/12 -> exceeds Nov 30 deadline
    $fortnights = $this->calendar->getEligibleFortnights();
    expect($fortnights)->toBeEmpty();
});

it('returns eligible benefits whose collection date has not passed in current year', function () {
    Carbon::setTestNow('2026-06-01');

    // Past benefit in current year (May 15)
    Benefit::factory()->create([
        'name' => 'Prestación Pasada',
        'collection_month' => 5,
        'collection_day' => 15,
        'is_active' => true,
    ]);

    // Future benefit in current year (December 20)
    Benefit::factory()->create([
        'name' => 'Aguinaldo Diciembre',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    $eligible = $this->calendar->getEligibleBenefits();
    $names = $eligible->pluck('name')->all();

    expect($names)->toContain('Aguinaldo Diciembre')
        ->not->toContain('Prestación Pasada');
});
