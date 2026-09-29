<?php

namespace App\Actions;

use App\Models\Theme;
use App\Models\UserRequest;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateUserRequestReceipt
{
    /**
     * Generate the PDF receipt for a user request.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function execute(UserRequest $userRequest)
    {
        $theme = Theme::find(1);
        $primaryColor = $theme ? $theme->color_primary : '#611232';

        return Pdf::loadView('pdf.request-receipt', [
            'userRequest' => $userRequest,
            'primaryColor' => $primaryColor,
            'logoSrc' => public_path('assets/images/logo.webp'),
        ]);
    }
}
