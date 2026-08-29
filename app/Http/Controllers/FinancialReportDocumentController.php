<?php

namespace App\Http\Controllers;

use App\Models\FinancialReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportDocumentController extends Controller
{
    /**
     * Display or download the document associated with the financial report.
     */
    public function show(Request $request, FinancialReport $record): StreamedResponse
    {
        $media = $record->getFirstMedia('financial_reports');

        abort_unless($media !== null, 404, 'Documento no encontrado.');

        if ($request->boolean('download')) {
            return $media->toResponse($request);
        }

        return $media->toInlineResponse($request);
    }
}
