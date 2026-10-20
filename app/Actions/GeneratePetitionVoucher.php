<?php

namespace App\Actions;

use App\Models\AnnualPetition;
use App\Models\PetitionRequest;
use App\Support\DocumentThemePalette;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Collection;

class GeneratePetitionVoucher
{
    public function __construct(
        protected readonly DocumentThemePalette $documentThemePalette,
    ) {}

    /**
     * Generate the member-facing receipt for one CURP of an annual petition.
     *
     * @param  Collection<int, PetitionRequest>  $petitionRequests
     */
    public function execute(AnnualPetition $annualPetition, string $curp, $petitionRequests): PDF
    {
        return DomPdf::loadView('pdf.petition-voucher', [
            'annualPetition' => $annualPetition,
            'petitionRequests' => $petitionRequests,
            'curp' => $curp,
            'documentColors' => $this->documentThemePalette->resolve(),
            'logoSrc' => public_path('assets/images/logo.webp'),
        ]);
    }
}
