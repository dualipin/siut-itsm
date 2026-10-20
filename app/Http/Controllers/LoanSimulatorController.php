<?php

namespace App\Http\Controllers;

use App\Actions\GenerateLoanSimulationPdf;
use App\Http\Requests\StoreLoanSimulationRequest;
use App\Models\Benefit;
use App\Services\LoanSimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoanSimulatorController extends Controller
{
    public function __construct(
        protected readonly LoanSimulationService $loanSimulationService,
        protected readonly GenerateLoanSimulationPdf $generateLoanSimulationPdf,
    ) {}

    /**
     * Show the public loan simulator page.
     */
    public function show(): View
    {
        $benefits = Benefit::active()
            ->orderBy('collection_month')
            ->orderBy('collection_day')
            ->get(['id', 'name', 'collection_day', 'collection_month'])
            ->map(fn (Benefit $benefit) => [
                'id' => $benefit->id,
                'name' => $benefit->name,
                'collectionDay' => $benefit->collection_day,
                'collectionMonth' => $benefit->collection_month,
                'collectionDate' => $benefit->collectionDateForYear(now()->year)->format('Y-m-d'),
            ])
            ->values();

        return view('landing.simulador-prestamo', [
            'benefits' => $benefits,
        ]);
    }

    /**
     * Run a loan simulation, cache the result for download, and return the PDF URL.
     */
    public function simulate(StoreLoanSimulationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $params = [
            'monthly_rate' => (float) $validated['monthly_rate'] / 100,
            'disbursement_date' => now()->format('Y-m-d'),
            'payment_methods' => [],
        ];

        $nominaAmount = (float) ($validated['nomina']['amount'] ?? 0);
        $nominaFortnights = (int) ($validated['nomina']['fortnights'] ?? 0);
        if ($nominaAmount > 0 && $nominaFortnights > 0 && ! empty($validated['nomina']['first_fortnight'])) {
            $params['payment_methods']['nomina'] = [
                'amount' => $nominaAmount,
                'fortnights' => $nominaFortnights,
                'first_fortnight' => $validated['nomina']['first_fortnight'],
            ];
        }

        if (! empty($validated['benefits'])) {
            $params['payment_methods']['benefits'] = array_map(
                fn (array $b) => [
                    'benefit_id' => (int) $b['benefit_id'],
                    'amount' => (float) $b['amount'],
                    'collection_date' => $b['collection_date'],
                ],
                $validated['benefits']
            );
        }

        $amortizationTable = $this->loanSimulationService->buildAmortizationTable($params);
        $summary = $this->loanSimulationService->buildFinancialRunSummary($params);

        // Cache the simulation data for 10 minutes so the PDF route can render it
        $token = Str::uuid()->toString();
        Cache::put('loan_simulation_'.$token, [
            'amortizationTable' => $amortizationTable,
            'summary' => $summary,
        ], now()->addMinutes(10));

        return response()->json([
            'pdf_url' => route('public.simulator.download', ['token' => $token]),
        ]);
    }

    /**
     * Download the cached simulation PDF.
     */
    public function download(string $token): Response
    {
        /** @var array{amortizationTable: array<int, array<string, mixed>>, summary: array<string, mixed>}|null $data */
        $data = Cache::get('loan_simulation_'.$token);

        abort_if($data === null, 404, 'La simulación ha expirado o no existe.');

        Cache::forget('loan_simulation_'.$token);

        return $this->generateLoanSimulationPdf->execute($data);
    }
}
