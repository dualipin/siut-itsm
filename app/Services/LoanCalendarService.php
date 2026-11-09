<?php

namespace App\Services;

use App\Models\Benefit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LoanCalendarService
{
    /**
     * Get the application window deadline for the given year (November 30, end of day).
     */
    public function applicationDeadline(?int $year = null): Carbon
    {
        return Carbon::create($year ?? now()->year, 11, 30)->endOfDay();
    }

    /**
     * Get the payroll payment deadline for the given year (December 31, end of day).
     */
    public function paymentDeadline(?int $year = null): Carbon
    {
        return Carbon::create($year ?? now()->year, 12, 31)->endOfDay();
    }

    /**
     * Check if the loan application window is open.
     * Deadline: November 30 (inclusive). From December 1, new loans are blocked.
     */
    public function isApplicationWindowOpen(?Carbon $date = null): bool
    {
        $date = $date ? $date->copy()->startOfDay() : now()->startOfDay();
        $deadline = $this->applicationDeadline($date->year);

        return $date->lte($deadline);
    }

    /**
     * Get the list of eligible fortnights for nómina.
     * Must respect:
     * 1. 20-day tolerance: candidate fortnight must be after now + 20 days.
     * 2. Application window deadline: loan applications close November 30 of current year.
     * 3. Payment deadline: cannot exceed December 31 of current year.
     * 4. Fortnight days: 15th and the last real calendar day of each month.
     *
     * @return array<int, array{date: string, label: string, day: int, month: int, year: int}>
     */
    public function getEligibleFortnights(?Carbon $date = null): array
    {
        $date = $date ? $date->copy()->startOfDay() : now()->startOfDay();
        $year = $date->year;
        $applicationDeadline = $this->applicationDeadline($year);
        $paymentDeadline = $this->paymentDeadline($year);

        if ($date->gt($applicationDeadline)) {
            return [];
        }

        $minDate = $date->copy()->addDays(20);
        $result = [];

        $current = $date->copy()->startOfMonth();
        while ($current->year === $year && $current->month <= 12) {
            // First fortnight: 15th
            $q1 = $current->copy()->setDay(15);
            if ($q1->isAfter($minDate) && $q1->lte($paymentDeadline)) {
                $result[] = $this->formatFortnight($q1);
            }

            // Second fortnight: last day of month
            $q2 = $current->copy()->endOfMonth()->startOfDay();
            if ($q2->isAfter($minDate) && $q2->lte($paymentDeadline)) {
                $result[] = $this->formatFortnight($q2);
            }

            $current->addMonthNoOverflow();
        }

        return $result;
    }

    /**
     * Get the first eligible fortnight.
     */
    public function getFirstEligibleFortnight(?Carbon $date = null): ?Carbon
    {
        $fortnights = $this->getEligibleFortnights($date);
        if (empty($fortnights)) {
            return null;
        }

        return Carbon::parse($fortnights[0]['date']);
    }

    /**
     * Get active benefits eligible for loan payment in the current year.
     * Only benefits whose collection date has not passed are returned.
     *
     * @return Collection<int, array{id: int, name: string, collection_date: string, collection_day: int, collection_month: int, calculation_method: string, requires_document: bool, document_instructions: ?string}>
     */
    public function getEligibleBenefits(?Carbon $date = null): Collection
    {
        $date = $date ? $date->copy()->startOfDay() : now()->startOfDay();

        if (! $this->isApplicationWindowOpen($date)) {
            return collect();
        }

        $year = $date->year;

        return Benefit::active()
            ->orderBy('collection_month')
            ->orderBy('collection_day')
            ->get()
            ->filter(function (Benefit $benefit) use ($date, $year) {
                $collectionDate = $benefit->collectionDateForYear($year);

                return $collectionDate->isAfter($date);
            })
            ->map(function (Benefit $benefit) use ($year) {
                return [
                    'id' => $benefit->id,
                    'name' => $benefit->name,
                    'collection_date' => $benefit->collectionDateForYear($year)->format('Y-m-d'),
                    'collection_day' => $benefit->collection_day,
                    'collection_month' => $benefit->collection_month,
                    'calculation_method' => $benefit->calculation_method,
                    'requires_document' => (bool) $benefit->requires_document,
                    'document_instructions' => $benefit->document_instructions,
                ];
            })
            ->values();
    }

    protected function formatFortnight(Carbon $date): array
    {
        $monthName = $date->translatedFormat('F');
        $label = "{$date->day} de ".ucfirst($monthName)." de {$date->year}";

        return [
            'date' => $date->format('Y-m-d'),
            'label' => $label,
            'day' => $date->day,
            'month' => $date->month,
            'year' => $date->year,
        ];
    }

    /**
     * Compute the next chronological fortnight date.
     * If day < 15, goes to 15th of current month.
     * If day == 15, goes to end of current month.
     * If day is end of month, goes to 15th of next month.
     */
    public function nextFortnight(Carbon $date): Carbon
    {
        $day = $date->day;

        if ($day < 15) {
            return $date->copy()->setDay(15);
        }

        if ($day === 15) {
            return $date->copy()->endOfMonth()->startOfDay();
        }

        return $date->copy()->addMonthNoOverflow()->setDay(15);
    }

    /**
     * Calculate the date on which the k-th fortnight falls, starting from $firstFortnight.
     * For k=1, this is $firstFortnight itself.
     * For k=2, this is the next fortnight after $firstFortnight, and so forth.
     */
    public function calculateFortnightDate(Carbon|string $firstFortnight, int $fortnights): Carbon
    {
        $current = Carbon::parse($firstFortnight)->copy();
        for ($i = 1; $i < $fortnights; $i++) {
            $current = $this->nextFortnight($current);
        }

        return $current;
    }
}
