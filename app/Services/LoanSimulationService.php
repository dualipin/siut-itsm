<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class LoanSimulationService
{
    /**
     * Build the full fortnight-by-fortnight amortization table.
     *
     * @param  array{
     *     monthly_rate: float,
     *     payment_methods: array{
     *         nomina?: array{fortnights: int, first_fortnight: string, amount: float},
     *         benefits?: array<int, array{benefit_id: int, name: string, amount: float, collection_date: string}>
     *     },
     *     disbursement_date: string
     * }  $params
     * @return array<int, array{
     *     period: int,
     *     label: string,
     *     date: string,
     *     opening_balance: float,
     *     principal: float,
     *     interest: float,
     *     payment: float,
     *     closing_balance: float,
     *     type: string
     * }>
     */
    public function buildAmortizationTable(array $params): array
    {
        $disbursement = Carbon::parse($params['disbursement_date']);
        $monthlyRate = (float) $params['monthly_rate'];
        $nominaDailyRate = $monthlyRate / 30;          // simple (método alemán) for nómina
        $benefitDailyRate = $this->dailyRate($monthlyRate); // equivalent for benefits

        $nomina = $params['payment_methods']['nomina'] ?? null;
        $benefitsPayments = $params['payment_methods']['benefits'] ?? [];

        $rows = [];
        $period = 0;

        // ── Nómina section (German simple interest, tasa mensual / 30 × días reales) ──
        if ($nomina !== null) {
            $amount = (float) ($nomina['amount'] ?? 0);
            $fortnights = (int) $nomina['fortnights'];
            $firstFortnight = Carbon::parse($nomina['first_fortnight']);
            $principalPerFortnight = round($amount / $fortnights, 2);
            $balance = $amount;

            // Period 0: extra days (disbursement → first fortnight)
            $extraDays = $disbursement->diffInDays($firstFortnight);
            if ($extraDays > 0) {
                $extraInterest = round($balance * $nominaDailyRate * $extraDays, 2);
                $period++;
                $rows[] = [
                    'period' => $period,
                    'label' => 'Días adicionales',
                    'date' => $firstFortnight->format('Y-m-d'),
                    'opening_balance' => $balance,
                    'principal' => 0.0,
                    'interest' => $extraInterest,
                    'payment' => $extraInterest,
                    'closing_balance' => $balance,
                    'type' => 'extra_days',
                ];
            }

            // Regular fortnights — days are real calendar days between consecutive quincenas
            $previousDate = $firstFortnight->copy();
            $currentDate = $this->nextFortnight($firstFortnight);
            for ($i = 0; $i < $fortnights; $i++) {
                $isLastFortnight = ($i === $fortnights - 1);

                // Real calendar days between the previous quincena and this one
                $days = $previousDate->diffInDays($currentDate);

                $interest = round($balance * $nominaDailyRate * $days, 2);

                // Last row absorbs any rounding remainder
                $principal = $isLastFortnight
                    ? round($balance, 2)
                    : $principalPerFortnight;

                $payment = round($principal + $interest, 2);
                $closingBalance = round($balance - $principal, 2);

                $period++;
                $rows[] = [
                    'period' => $period,
                    'label' => 'Quincena '.($i + 1),
                    'date' => $currentDate->format('Y-m-d'),
                    'opening_balance' => round($balance, 2),
                    'principal' => $principal,
                    'interest' => $interest,
                    'payment' => $payment,
                    'closing_balance' => max(0.0, $closingBalance),
                    'type' => 'nomina',
                ];

                $balance = max(0.0, $closingBalance);
                $previousDate = $currentDate->copy();
                $currentDate = $this->nextFortnight($currentDate);
            }
        }

        // ── Benefit rows ──────────────────────────────────────────────────────────
        // Growth is tracked fortnight-by-fortnight using the equivalent daily rate.
        // Each intermediate fortnight produces a 'benefit_growth' row (payment = 0);
        // the last fortnight produces the final 'benefit' row.
        foreach ($benefitsPayments as $benefitPayment) {
            $collectionDate = Carbon::parse($benefitPayment['collection_date']);
            $benefitAmount = (float) $benefitPayment['amount'];
            $benefitName = $benefitPayment['name'] ?? 'Prestación';

            // Resolve last nómina date (if any) to apply D5
            $lastNominaDate = null;
            if ($nomina !== null) {
                $lastNominaDate = Carbon::parse($nomina['first_fortnight'])->copy();
                for ($i = 1; $i < (int) $nomina['fortnights']; $i++) {
                    $lastNominaDate = $this->nextFortnight($lastNominaDate);
                }
            }

            $baseDate = $this->benefitBaseDate($disbursement, $lastNominaDate, $collectionDate);

            // Iterate fortnight-by-fortnight from baseDate to collectionDate
            $balance = $benefitAmount;
            $current = $baseDate->copy();
            $quincenal = 1;

            while (true) {
                $next = $this->nextFortnight($current);
                $isLast = $next->greaterThanOrEqualTo($collectionDate);
                $rowEnd = $isLast ? $collectionDate->copy() : $next->copy();

                $days = $current->diffInDays($rowEnd);
                $interest = round($balance * (pow(1 + $benefitDailyRate, $days) - 1), 2);
                $newBalance = round($balance + $interest, 2);

                $period++;

                if ($isLast) {
                    $rows[] = [
                        'period' => $period,
                        'label' => $benefitName,
                        'date' => $rowEnd->format('Y-m-d'),
                        'opening_balance' => round($balance, 2),
                        'principal' => round($benefitAmount, 2),
                        'interest' => round($newBalance - $benefitAmount, 2),
                        'payment' => $newBalance,
                        'closing_balance' => 0.0,
                        'type' => 'benefit',
                    ];
                    break;
                }

                $rows[] = [
                    'period' => $period,
                    'label' => $benefitName.' — Q'.$quincenal,
                    'date' => $rowEnd->format('Y-m-d'),
                    'opening_balance' => round($balance, 2),
                    'principal' => 0.0,
                    'interest' => $interest,
                    'payment' => 0.0,
                    'closing_balance' => $newBalance,
                    'type' => 'benefit_growth',
                ];

                $balance = $newBalance;
                $current = $next->copy();
                $quincenal++;
            }
        }

        return $rows;
    }

    /**
     * Equivalent daily rate from a monthly rate: (1 + r_m)^(1/30) − 1.
     */
    private function dailyRate(float $monthlyRate): float
    {
        return pow(1 + $monthlyRate, 1 / 30) - 1;
    }

    /**
     * Determine the base date for a benefit's compound period (decision D5).
     *
     * If the last nómina date is within 15 days of the collection date, use
     * the disbursement date instead (the nómina barely overlaps the benefit).
     */
    private function benefitBaseDate(Carbon $disbursement, ?Carbon $lastNomina, Carbon $collectionDate): Carbon
    {
        if ($lastNomina !== null && $lastNomina->diffInDays($collectionDate) > 15) {
            return $lastNomina->copy();
        }

        return $disbursement->copy();
    }

    /**
     * Build the financial run summary totals.
     *
     * @param  array{
     *     monthly_rate: float,
     *     payment_methods: array{
     *         nomina?: array{fortnights: int, first_fortnight: string, amount: float},
     *         benefits?: array<int, array{benefit_id: int, amount: float, collection_date: string}>
     *     },
     *     disbursement_date: string
     * }  $params
     * @return array{
     *     amount: float,
     *     monthly_rate: float,
     *     monthly_rate_percent: string,
     *     total_interest: float,
     *     total_principal: float,
     *     total_payment: float,
     *     disbursement_date: string,
     *     fortnights: int|null,
     *     first_fortnight: string|null,
     *     last_fortnight: string|null
     * }
     */
    public function buildFinancialRunSummary(array $params): array
    {
        $table = $this->buildAmortizationTable($params);

        $totalInterest = round(array_sum(array_column($table, 'interest')), 2);
        $totalPrincipal = round(array_sum(array_column($table, 'principal')), 2);
        $totalPayment = round(array_sum(array_column($table, 'payment')), 2);

        $nomina = $params['payment_methods']['nomina'] ?? null;
        $fortnights = $nomina ? (int) $nomina['fortnights'] : null;
        $firstFortnight = $nomina ? $nomina['first_fortnight'] : null;

        $lastFortnight = null;
        if ($nomina !== null) {
            $date = Carbon::parse($nomina['first_fortnight']);
            for ($i = 1; $i < (int) $nomina['fortnights']; $i++) {
                $date = $this->nextFortnight($date);
            }
            $lastFortnight = $date->format('Y-m-d');
        }

        $nominaAmount = $nomina ? (float) ($nomina['amount'] ?? 0) : 0.0;
        $benefitsTotal = array_sum(
            array_map(fn (array $b) => (float) $b['amount'], $params['payment_methods']['benefits'] ?? [])
        );

        return [
            'amount' => round($nominaAmount + $benefitsTotal, 2),
            'monthly_rate' => (float) $params['monthly_rate'],
            'monthly_rate_percent' => number_format((float) $params['monthly_rate'] * 100, 2).' %',
            'total_interest' => $totalInterest,
            'total_principal' => $totalPrincipal,
            'total_payment' => $totalPayment,
            'disbursement_date' => $params['disbursement_date'],
            'fortnights' => $fortnights,
            'first_fortnight' => $firstFortnight,
            'last_fortnight' => $lastFortnight,
        ];
    }

    /**
     * Advance one fortnight from the given date.
     * Fortnights land on the 15th or the last day of the month.
     */
    private function nextFortnight(Carbon $date): Carbon
    {
        $day = $date->day;

        if ($day < 15) {
            return $date->copy()->setDay(15);
        }

        // Currently on the 15th → move to last day of month
        if ($day === 15) {
            return $date->copy()->endOfMonth()->startOfDay();
        }

        // Currently on last day of month → move to 15th of next month
        return $date->copy()->addMonthNoOverflow()->setDay(15);
    }
}
