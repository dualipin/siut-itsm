<?php

namespace App\Actions;

use App\Models\User;
use App\Support\DocumentThemePalette;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class GenerateUserCredentialPdf
{
    public function __construct(
        protected readonly DocumentThemePalette $documentThemePalette,
    ) {}

    /**
     * Generate the user credential card PDF (front and back).
     */
    public function execute(User $user): Response
    {
        $palette = $this->documentThemePalette->resolve();

        $theme = [
            'primary' => $palette['primary'],
            'secondary' => $palette['secondary'],
            'text' => $palette['base_content'],
            'muted' => $palette['base_300'],
            'border' => $palette['base_300'],
            'surface' => $palette['base_100'],
            'background' => $palette['base_200'],
            'onPrimary' => $palette['primary_content'],
            'onSecondary' => $palette['secondary_content'],
        ];

        $bgPath = public_path('assets/images/background-id.png');
        $backgroundImage = file_exists($bgPath) ? $this->imageToBase64($bgPath) : null;

        $logos = [
            'tecnm' => file_exists(public_path('assets/img/logo/logo-tecnm.png'))
                ? $this->imageToBase64(public_path('assets/img/logo/logo-tecnm.png'))
                : null,
            'itsm' => file_exists(public_path('assets/img/logo/logo-itsm.jpg'))
                ? $this->imageToBase64(public_path('assets/img/logo/logo-itsm.jpg'))
                : null,
            'siut' => file_exists(public_path('assets/images/logo.webp'))
                ? $this->imageToBase64(public_path('assets/images/logo.webp'))
                : null,
        ];

        $photoSrc = null;
        if ($user->photo_path) {
            if (Storage::disk('public')->exists($user->photo_path)) {
                $photoSrc = $this->imageToBase64(Storage::disk('public')->path($user->photo_path));
            } elseif (file_exists(public_path($user->photo_path))) {
                $photoSrc = $this->imageToBase64(public_path($user->photo_path));
            }
        }

        $holder = [
            'fullName' => $user->full_name ?: trim("{$user->name} {$user->surnames}"),
            'cargo' => $user->category ?: 'Afiliado',
            'imss' => $user->nss ?: 'No registrado',
            'photoSrc' => $photoSrc,
            'vigencia' => (string) ($user->hiring_date?->addYear()->year ?? now()->year),
        ];

        // ponytail: firmante hardcodeado, crear modelo Signatory cuando haya requisito real
        $signatory = [
            'name' => 'Secretario General',
            'role' => 'SIUT-ITSM',
            'signatureHash' => substr(hash('sha256', ($user->curp ?? '').$user->id), 0, 32),
        ];

        // ponytail: QR apunta al sitio; ruta /verificar/{token} es scope separado
        $qrSrc = null;

        $curpPart = filled($user->curp) ? $user->curp : "usuario-{$user->id}";
        $filename = "credencial-{$curpPart}.pdf";

        /** @var Response $response */
        $response = DomPdf::loadView('pdf.credential', [
            'theme' => $theme,
            'backgroundImage' => $backgroundImage,
            'logos' => $logos,
            'holder' => $holder,
            'organization' => (string) config('syndicate.name', 'Sindicato Único de Trabajadores del ITSM'),
            'website' => (string) config('app.url'),
            'rfc' => '',
            'address' => (string) config('syndicate.address', ''),
            'issuedAt' => now()->format('d/m/Y'),
            'signatory' => $signatory,
            'qrSrc' => $qrSrc,
        ])->download($filename);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Convert an image file to a base64 data URI for reliable embedding in DomPDF.
     */
    protected function imageToBase64(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        $content = (string) file_get_contents($path);

        return "data:{$mime};base64,".base64_encode($content);
    }
}
