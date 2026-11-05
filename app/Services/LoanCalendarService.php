<?php

namespace App\Services;

use App\Models\Benefit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LoanCalendarService
{
    /**
     * Check if the loan application window is open.
     * Deadline: November 30 (inclusive). From December 1, new loans are blocked.
     */
    public function isApplicationWindowOpen(?Carbon $date = null): bool
    {
        $date = $date ? $date->copy()->startOfDay() : now()->startOfDay();
        $deadline = Carbon::create($date->year, 11, 30)->endOfDay();

        return $date->lte($deadline);
    }

    /**
     * Get the list of eligible fortnights for nómina.
     * Must respect:
     * 1. 20-day tolerance: candidate fortnight must be after now + 20 days.
     * 2. Year deadline: cannot exceed November 30 of current year.
     * 3. Fortnight days: 15th and the last real calendar day of each month.
     *
     * @return array<int, array{date: string, label: string, day: int, month: int, year: int}>
     */
    public function getEligibleFortnights(?Carbon $date = null): array
    {
        $date = $date ? $date->copy()->startOfDay() : now()->startOfDay();
        $year = $date->year;
        $deadline = Carbon::create($year, 11, 30)->endOfDay();

        if ($date->gt($deadline)) {
            return [];
        }

        $minDate = $date->copy()->addDays(20);
        $result = [];

        $current = $date->copy()->startOfMonth();
        while ($current->year === $year && $current->month <= 11) {
            // First fortnight: 15th
            $q1 = $current->copy()->setDay(15);
            if ($q1->isAfter($minDate) && $q1->lte($deadline)) {
                $result[] = $this->formatFortnight($q1);
            }

            // Second fortnight: last day of month
            $q2 = $current->copy()->endOfMonth()->startOfDay();
            if ($q2->isAfter($minDate) && $q2->lte($deadline)) {
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
}
