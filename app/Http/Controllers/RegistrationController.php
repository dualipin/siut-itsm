<?php

namespace App\Http\Controllers;

use App\Actions\RegisterAgremiado;
use App\Enums\UserDocumentType;
use App\Http\Requests\RegisterAgremiadoRequest;
use App\Http\Requests\StoreRegistrationDocumentRequest;
use App\Services\DocumentAIService;
use App\Services\IneValidationService;
use App\Services\RegistrationStaging;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * Display the registration page.
     * Redirects to portal if already authenticated.
     */
    public function index(Request $request): mixed
    {
        if (Auth::check()) {
            return redirect('/portal');
        }

        return view('landing.register');
    }

    /**
     * Store a temporary document upload and trigger DocumentAI analysis.
     */
    public function storeDocument(
        StoreRegistrationDocumentRequest $request,
        RegistrationStaging $staging,
        DocumentAIService $aiService,
        IneValidationService $ineValidator,
    ): JsonResponse {
        $file = $request->file('file');
        $kind = (string) $request->input('kind');

        $uuid = (string) Str::uuid();
        $ext = $file->getClientOriginalExtension() ?: ($kind === 'photo' ? 'jpg' : 'pdf');
        $filename = "{$uuid}.{$ext}";
        $path = $file->storeAs('temp-uploads/registro', $filename, 'local');

        if ($kind === 'photo') {
            $token = $staging->stage('photo', $path, null, []);

            return response()->json([
                'token' => $token,
                'fields' => (object) [],
                'warnings' => [],
                'ai_status' => 'not_applicable',
            ]);
        }

        $fullPath = Storage::disk('local')->path($path);
        $bytes = (string) file_get_contents($fullPath);
        $fileHash = hash('sha256', $bytes);
        $mimeType = $file->getClientMimeType() ?: (mime_content_type($fullPath) ?: 'application/octet-stream');
        $throttleKey = 'ip:'.$request->ip();

        if ($kind === 'ine_reverso') {
            ['result' => $result, 'warnings' => $warnings] = $aiService->reviewIneBack($bytes, $mimeType, $throttleKey);
        } else {
            $type = $kind === 'ine' ? UserDocumentType::Ine : UserDocumentType::ComprobantePago;
            ['result' => $result, 'warnings' => $warnings] = $aiService->review($bytes, $mimeType, $type, $throttleKey);
        }

        $fields = [];
        $aiStatus = 'failed';
        $aiArray = $result?->toArray();

        if ($result !== null) {
            $aiStatus = $result->isValidDocument ? 'success' : 'warning';
            $fields = $result->extractedFields;

            // Normalize birth date to ISO format YYYY-MM-DD for HTML5 date input compatibility
            if ($kind === 'ine' && isset($fields['fecha_nacimiento'])) {
                $fields['fecha_nacimiento'] = $ineValidator->normalizeDateString((string) $fields['fecha_nacimiento']);
                $aiArray['extracted_fields']['fecha_nacimiento'] = $fields['fecha_nacimiento'];
            }

            if ($kind === 'ine_reverso' && isset($fields['fecha_nacimiento_mrz'])) {
                $fields['fecha_nacimiento_mrz'] = $ineValidator->normalizeDateString((string) $fields['fecha_nacimiento_mrz']);
                $aiArray['extracted_fields']['fecha_nacimiento_mrz'] = $fields['fecha_nacimiento_mrz'];
            }
        }

        // Strict blocking validation for INE front and back (degrades gracefully when AI is unavailable)
        if ($kind === 'ine' || $kind === 'ine_reverso') {
            $validationError = $kind === 'ine'
                ? $ineValidator->validateFront($aiArray)
                : $ineValidator->validateBack($aiArray);

            if ($validationError === null) {
                $pairedToken = (string) $request->input('paired_token', '');

                if ($pairedToken !== '') {
                    $pairedKind = $kind === 'ine' ? 'ine_reverso' : 'ine';
                    $pairedEntry = $staging->resolve($pairedToken, $pairedKind);
                    $currentCandidate = ['hash' => $fileHash, 'ai' => $aiArray];

                    $validationError = $kind === 'ine'
                        ? $ineValidator->validatePair($currentCandidate, $pairedEntry)
                        : $ineValidator->validatePair($pairedEntry, $currentCandidate);
                }
            }

            if ($validationError !== null) {
                Storage::disk('local')->delete($path);

                return response()->json([
                    'message' => $validationError,
                    'errors' => [
                        'file' => [$validationError],
                    ],
                ], 422);
            }
        } elseif ($result !== null) {
            if (! $result->isValidDocument) {
                $warnings[] = filled($result->observations)
                    ? $result->observations
                    : 'El archivo no parece ser un comprobante de pago válido. Verifica que hayas subido el documento correcto.';
            }

            $fechaPago = $fields['fecha_pago'] ?? '';
            if (filled($fechaPago)) {
                try {
                    $payDate = Carbon::parse($fechaPago);
                    if ($payDate->diffInDays(now()) > 31) {
                        $warnings[] = 'El comprobante de pago parece tener más de 31 días de antigüedad ('.$payDate->toDateString().'). Solo se aceptan comprobantes del mes en curso.';
                    }
                } catch (\Exception) {
                    // Ignore unparseable
                }
            }
        }

        if ($result === null && empty($warnings)) {
            $warnings[] = $kind === 'ine_reverso'
                ? 'No fue posible verificar el reverso de la INE automáticamente. Será revisado manualmente por la administración.'
                : 'No se pudieron extraer datos automáticamente. Puedes completar el formulario de forma manual.';
        }

        $token = $staging->stage($kind, $path, $aiArray, $warnings, $fileHash);

        return response()->json([
            'token' => $token,
            'fields' => (object) $fields,
            'warnings' => array_values(array_unique($warnings)),
            'ai_status' => $aiStatus,
        ]);
    }

    /**
     * Handle the full registration submission.
     */
    public function store(
        RegisterAgremiadoRequest $request,
        RegisterAgremiado $action
    ): JsonResponse {
        $validated = $request->validated();
        $user = $action->execute($validated, session());

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'redirect' => '/portal',
            'message' => '¡Registro completado con éxito!',
        ]);
    }
}
