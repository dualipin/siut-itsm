<?php

namespace App\Actions;

use App\Support\DocumentThemePalette;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Response;

class GenerateLoanSimulationPdf
{
    public function __construct(
        protected readonly DocumentThemePalette $documentThemePalette,
    ) {}

    /**
     * Generate a combined simulation PDF (financial run + amortization table).
     *
     * @param  array{
     *     amortizationTable: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>
     * }  $simulationData
     */
    public function execute(array $simulationData, string $token = ''): Response
    {
        $suffix = $token !== '' ? '-'.substr($token, 0, 8) : '';
        $filename = "simulacion-prestamo{$suffix}.pdf";

        /** @var Response */
        $response = DomPdf::loadView('pdf.loan-simulation-combined', [
            'summary' => $simulationData['summary'],
            'amortizationTable' => $simulationData['amortizationTable'],
            'documentColors' => $this->documentThemePalette->resolve(),
            'logoSrc' => public_path('assets/images/logo.webp'),
        ])->download($filename);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
