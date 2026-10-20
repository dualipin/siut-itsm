<?php

namespace App\Actions;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Response;

class GenerateLoanSimulationPdf
{
    /**
     * Generate a combined simulation PDF (financial run + amortization table).
     *
     * @param  array{
     *     amortizationTable: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>
     * }  $simulationData
     */
    public function execute(array $simulationData): Response
    {
        /** @var Response */
        return DomPdf::loadView('pdf.loan-simulation-combined', [
            'summary' => $simulationData['summary'],
            'amortizationTable' => $simulationData['amortizationTable'],
            'primaryColor' => '#611232',
            'logoSrc' => public_path('assets/images/logo.webp'),
        ])->download('simulacion-prestamo.pdf');
    }
}
