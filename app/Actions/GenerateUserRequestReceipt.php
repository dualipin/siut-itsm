<?php

namespace App\Actions;

use App\Models\UserRequest;
use App\Support\DocumentThemePalette;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateUserRequestReceipt
{
    public function __construct(
        protected readonly DocumentThemePalette $documentThemePalette,
    ) {}

    /**
     * Generate the PDF receipt for a user request.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function execute(UserRequest $userRequest)
    {
        return Pdf::loadView('pdf.request-receipt', [
            'userRequest' => $userRequest,
            'documentColors' => $this->documentThemePalette->resolve(),
            'logoSrc' => public_path('assets/images/logo.webp'),
        ]);
    }
}
