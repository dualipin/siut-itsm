<?php

use App\Enums\LoanOperationalState;
use App\Models\Benefit;
use App\Models\User;
use App\Services\LoanCalendarService;
use App\Services\LoanOperationalStateService;
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

it('keeps application window open in december while Activo as long as at least one fortnight or future benefit exists', function () {
    // Dec 5: 2026-12-05 + 20 days = 2026-12-25 -> Dec 31 fortnight is eligible even without benefits
    Carbon::setTestNow('2026-12-05 12:00:00');
    expect($this->calendar->isApplicationWindowOpen())->toBeTrue();

    // Dec 10: 2026-12-10 + 20 days = 2026-12-30 -> Dec 31 fortnight is still eligible
    Carbon::setTestNow('2026-12-10 12:00:00');
    expect($this->calendar->isApplicationWindowOpen())->toBeTrue();

    // Dec 15 without any future benefit: no fortnights and no benefits -> closed with no-dates message
    Carbon::setTestNow('2026-12-15 12:00:00');
    expect($this->calendar->isApplicationWindowOpen())->toBeFalse()
        ->and($this->calendar->applicationClosedReason())
        ->toContain('No hay fechas disponibles')
        ->toContain('Cierre de año')
        ->not->toContain('30 de noviembre');

    // Dec 15 WITH an active future benefit (Dec 20): open
    Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    expect($this->calendar->isApplicationWindowOpen())->toBeTrue();
});

it('closes application window when operational state is TemporalmenteFueraDeServicio even with eligible dates', function () {
    Carbon::setTestNow('2026-06-01');
    $admin = User::factory()->admin()->create(['is_active' => true]);

    app(LoanOperationalStateService::class)->updateState(
        $admin,
        LoanOperationalState::TemporalmenteFueraDeServicio,
        'Cierre temporal por mantenimiento.',
    );

    expect($this->calendar->isApplicationWindowOpen())->toBeFalse()
        ->and($this->calendar->getEligibleFortnights())->toBeEmpty()
        ->and($this->calendar->getEligibleBenefits())->toBeEmpty()
        ->and($this->calendar->applicationClosedReason())->toBe('Cierre temporal por mantenimiento.');
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

it('returns december fortnights up to december 31 when applying before or on november 30', function () {
    // 15/11 + 20 days = 05/12 -> eligible are 15/12 and 31/12
    Carbon::setTestNow('2026-11-15');
    $fortnights = $this->calendar->getEligibleFortnights();
    expect($fortnights)->toHaveCount(2)
        ->and($fortnights[0]['date'])->toBe('2026-12-15')
        ->and($fortnights[1]['date'])->toBe('2026-12-31');

    // 30/11 + 20 days = 20/12 -> eligible is 31/12 only
    Carbon::setTestNow('2026-11-30');
    $fortnightsNov30 = $this->calendar->getEligibleFortnights();
    expect($fortnightsNov30)->toHaveCount(1)
        ->and($fortnightsNov30[0]['date'])->toBe('2026-12-31');
});

it('returns december 31 fortnight in early december while Activo and returns empty once 20-day tolerance passes december 31', function () {
    Carbon::setTestNow('2026-12-05');
    $fortnightsDec5 = $this->calendar->getEligibleFortnights();
    expect($fortnightsDec5)->toHaveCount(1)
        ->and($fortnightsDec5[0]['date'])->toBe('2026-12-31');

    Carbon::setTestNow('2026-12-11');
    expect($this->calendar->getEligibleFortnights())->toBeEmpty();

    Carbon::setTestNow('2026-12-15');
    expect($this->calendar->getEligibleFortnights())->toBeEmpty();
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

it('returns eligible benefits in december while Activo when collection date is after today in current year', function () {
    Carbon::setTestNow('2026-12-05');

    Benefit::factory()->create([
        'name' => 'Aguinaldo Diciembre',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    Benefit::factory()->create([
        'name' => 'Prestación Pasada',
        'collection_month' => 12,
        'collection_day' => 1,
        'is_active' => true,
    ]);

    $eligible = $this->calendar->getEligibleBenefits();
    expect($eligible->pluck('name')->all())->toBe(['Aguinaldo Diciembre']);
});

it('calculates the completion date for k fortnights correctly', function () {
    // 1 quincena on 2026-11-30 completes on 2026-11-30
    $end1 = $this->calendar->calculateFortnightDate('2026-11-30', 1);
    expect($end1->format('Y-m-d'))->toBe('2026-11-30');

    // 2 quincenas on 2026-11-30 completes on 2026-12-15
    $end2 = $this->calendar->calculateFortnightDate('2026-11-30', 2);
    expect($end2->format('Y-m-d'))->toBe('2026-12-15');

    // 3 quincenas on 2026-11-30 completes on 2026-12-31
    $end3 = $this->calendar->calculateFortnightDate('2026-11-30', 3);
    expect($end3->format('Y-m-d'))->toBe('2026-12-31');

    // 1 quincena starting 2026-06-15 completes on 2026-06-15
    $endMid1 = $this->calendar->calculateFortnightDate('2026-06-15', 1);
    expect($endMid1->format('Y-m-d'))->toBe('2026-06-15');

    // 2 quincenas starting 2026-06-15 completes on 2026-06-30
    $endMid2 = $this->calendar->calculateFortnightDate('2026-06-15', 2);
    expect($endMid2->format('Y-m-d'))->toBe('2026-06-30');
});

it('projects fortnights and benefits strictly to target fiscal year during Cierre de año and handles december 28 and january 1 transition', function () {
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $stateService = app(LoanOperationalStateService::class);

    Benefit::factory()->create([
        'name' => 'Día del Maestro',
        'collection_month' => 5,
        'collection_day' => 15,
        'is_active' => true,
    ]);
    Benefit::factory()->create([
        'name' => 'Aguinaldo',
        'collection_month' => 12,
        'collection_day' => 20,
        'is_active' => true,
    ]);

    // 1. November 15, 2026 in Cierre de año (target 2027) -> excludes Dec 2026, projects all 24 fortnights & both benefits to 2027
    Carbon::setTestNow('2026-11-15 10:00:00');
    $stateService->updateState($admin, LoanOperationalState::CierreAnio);

    $novFortnights = $this->calendar->getEligibleFortnights();
    $novBenefits = $this->calendar->getEligibleBenefits();

    expect($this->calendar->isApplicationWindowOpen())->toBeTrue()
        ->and($this->calendar->paymentDeadline()->format('Y-m-d'))->toBe('2027-12-31')
        ->and($novFortnights)->toHaveCount(24)
        ->and($novFortnights[0]['date'])->toBe('2027-01-15')
        ->and($novFortnights[23]['date'])->toBe('2027-12-31')
        ->and($novBenefits->pluck('collection_date')->all())->toBe(['2027-05-15', '2027-12-20']);

    // 2. December 15, 2026 in Cierre de año (target 2027) -> excludes 2026-12-20 Aguinaldo, starts at 2027-01-15
    Carbon::setTestNow('2026-12-15 10:00:00');
    $dec15Fortnights = $this->calendar->getEligibleFortnights();
    $dec15Benefits = $this->calendar->getEligibleBenefits();

    expect($dec15Fortnights)->toHaveCount(24)
        ->and($dec15Fortnights[0]['date'])->toBe('2027-01-15')
        ->and($dec15Benefits->pluck('collection_date')->all())->toBe(['2027-05-15', '2027-12-20']);

    // 3. December 28, 2026 edge case (+20 days = 2027-01-17) -> skips 2027-01-15 and offers 2027-01-31
    Carbon::setTestNow('2026-12-28 10:00:00');
    $dec28Fortnights = $this->calendar->getEligibleFortnights();

    expect($dec28Fortnights)->toHaveCount(23)
        ->and($dec28Fortnights[0]['date'])->toBe('2027-01-31')
        ->and($this->calendar->getFirstEligibleFortnight()?->format('Y-m-d'))->toBe('2027-01-31');

    // 4. January 1, 2027 auto-transition -> Activo for 2027 (not 2028), first fortnight 2027-01-31
    Carbon::setTestNow('2027-01-01 00:00:01');

    expect($stateService->effectiveState())->toBe(LoanOperationalState::Activo)
        ->and($stateService->effectiveFiscalYear())->toBe(2027)
        ->and($this->calendar->paymentDeadline()->format('Y-m-d'))->toBe('2027-12-31')
        ->and($this->calendar->getFirstEligibleFortnight()?->format('Y-m-d'))->toBe('2027-01-31')
        ->and($this->calendar->getEligibleBenefits()->pluck('collection_date')->all())->toBe(['2027-05-15', '2027-12-20']);
});
