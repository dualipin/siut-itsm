<?php

namespace App\Actions;

use App\Models\AnnualPetition;
use App\Models\PetitionRequest;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Collection;

class GeneratePetitionConsolidatedReport
{
    /**
     * Generate the admin-facing consolidated report for a whole annual petition.
     *
     * Rows are streamed with chunkById so the Eloquent layer never holds every
     * record at once. The rendered DOM still scales with the row count, so a
     * very large year should be offloaded to a queued job.
     */
    public function execute(AnnualPetition $annualPetition): PDF
    {
        $petitionRequests = new Collection;

        PetitionRequest::query()
            ->where('annual_petition_id', $annualPetition->id)
            ->orderBy('id')
            ->chunkById(500, function ($chunk) use ($petitionRequests): void {
                $petitionRequests->push(...$chunk);
            });

        return DomPdf::loadView('pdf.consolidated-report', [
            'annualPetition' => $annualPetition,
            'petitionRequests' => $petitionRequests,
            'primaryColor' => '#611232',
            'logoSrc' => public_path('assets/images/logo.webp'),
        ]);
    }
}
