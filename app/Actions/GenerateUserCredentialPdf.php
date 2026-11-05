<?php

namespace App\Actions;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateUserCredentialPdf
{
    /**
     * Generate the user credential card PDF (front and back).
     */
    public function execute(User $user): StreamedResponse
    {
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
        $website = 'www.siutitsm.com.mx';
        $qrSrc = $this->generateQrDataUri("https://{$website}");

        $curpPart = filled($user->curp) ? $user->curp : "usuario-{$user->id}";
        $filename = "credencial-{$curpPart}.pdf";

        $pdf = DomPdf::loadView('pdf.credential', [
            'backgroundImage' => $backgroundImage,
            'logos' => $logos,
            'holder' => $holder,
            'organization' => (string) config('syndicate.name', 'Sindicato Único de Trabajadores del ITSM'),
            'website' => $website,
            'rfc' => '',
            'address' => (string) config('syndicate.address', ''),
            'issuedAt' => now()->format('d/m/Y'),
            'signatory' => $signatory,
            'qrSrc' => $qrSrc,
        ]);

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
            [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
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

    /**
     * Generate a QR code PNG data URI for the credential.
     */
    protected function generateQrDataUri(string $data): ?string
    {
        try {
            $options = new QROptions([
                'outputType' => QROutputInterface::GDIMAGE_PNG,
                'eccLevel' => EccLevel::M,
                'scale' => 8,
                'imageBase64' => true,
            ]);

            return (new QRCode($options))->render($data);
        } catch (\Throwable) {
            return null;
        }
    }
}
