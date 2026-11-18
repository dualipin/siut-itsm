<?php

namespace App\Http\Controllers;

use App\Actions\GenerateLoanSimulationPdf;
use App\Enums\LoanOperationalState;
use App\Filament\Resources\Loans\Pages\ApplyLoan;
use App\Http\Requests\StoreLoanSimulationRequest;
use App\Services\LoanCalendarService;
use App\Services\LoanOperationalStateService;
use App\Services\LoanSimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LoanSimulatorController extends Controller
{
    public function __construct(
        protected readonly LoanSimulationService $loanSimulationService,
        protected readonly GenerateLoanSimulationPdf $generateLoanSimulationPdf,
        protected readonly LoanOperationalStateService $operationalStateService,
        protected readonly LoanCalendarService $calendarService,
    ) {}

    /**
     * Show the public loan simulator page.
     */
    public function show(): View
    {
        $isOutOfService = $this->operationalStateService->isTemporarilyOutOfService();
        $effectiveState = $this->operationalStateService->effectiveState();
        $targetFiscalYear = $this->operationalStateService->effectiveFiscalYear();
        $hasAvailableDates = $this->calendarService->isApplicationWindowOpen();

        return view('landing.simulador-prestamo', [
            'benefits' => $this->calendarService->getSimulatorBenefits($targetFiscalYear),
            'operationalState' => $effectiveState->value,
            'targetFiscalYear' => $targetFiscalYear,
            'closedFiscalYear' => now()->year,
            'isYearEndClose' => $effectiveState === LoanOperationalState::CierreAnio,
            'isOutOfService' => $isOutOfService,
            'suspensionMessage' => $this->operationalStateService->publicSuspensionMessage(),
            'hasAvailableDates' => $hasAvailableDates,
            'windowClosedMessage' => (! $isOutOfService && ! $hasAvailableDates)
                ? $this->calendarService->applicationClosedReason()
                : null,
        ]);
    }

    /**
     * Run a loan simulation, cache the result for download, and return the PDF URL.
     */
    public function simulate(StoreLoanSimulationRequest $request): JsonResponse
    {
        $token = $this->loanSimulationService->simulateAndCache($request->validated());

        return response()->json([
            'pdf_url' => route('public.simulator.download', ['token' => $token]),
            'application_url' => ApplyLoan::getUrl(['simulation' => $token], panel: 'portal'),
        ]);
    }

    /**
     * Download the cached simulation PDF.
     */
    public function download(string $token): Response
    {
        if ($this->operationalStateService->isTemporarilyOutOfService()) {
            abort(503, $this->operationalStateService->publicSuspensionMessage());
        }

        /** @var array{amortizationTable: array<int, array<string, mixed>>, summary: array<string, mixed>}|null $data */
        $data = Cache::get('loan_simulation_'.$token);

        abort_if($data === null, 404, 'La simulación ha expirado o no existe. Vuelve al simulador para generar una nueva.');

        Cache::forget('loan_simulation_'.$token);

        return $this->generateLoanSimulationPdf->execute($data, $token);
    }
}
