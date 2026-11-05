<?php

namespace App\Http\Controllers;

use App\Actions\RegisterAgremiado;
use App\Enums\UserDocumentType;
use App\Http\Requests\RegisterAgremiadoRequest;
use App\Http\Requests\StoreRegistrationDocumentRequest;
use App\Services\DocumentAIService;
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
        DocumentAIService $aiService
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

        $type = $kind === 'ine' ? UserDocumentType::Ine : UserDocumentType::ComprobantePago;
        $fullPath = Storage::disk('local')->path($path);
        $bytes = (string) file_get_contents($fullPath);
        $mimeType = $file->getClientMimeType() ?: (mime_content_type($fullPath) ?: 'application/octet-stream');
        $throttleKey = 'ip:'.$request->ip();

        ['result' => $result, 'warnings' => $warnings] = $aiService->review($bytes, $mimeType, $type, $throttleKey);

        $fields = [];
        $aiStatus = 'failed';

        if ($result !== null) {
            $aiStatus = $result->isValidDocument ? 'success' : 'warning';
            $fields = $result->extractedFields;

            if (! $result->isValidDocument) {
                $docName = $type === UserDocumentType::Ine ? 'una credencial INE' : 'un comprobante de pago';
                $warnings[] = filled($result->observations)
                    ? $result->observations
                    : "El archivo no parece ser {$docName} válido. Verifica que hayas subido el documento correcto.";
            }

            // Normalize birth date to ISO format YYYY-MM-DD for HTML5 date input compatibility
            if ($type === UserDocumentType::Ine && isset($fields['fecha_nacimiento'])) {
                if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', (string) $fields['fecha_nacimiento'], $m)) {
                    $fields['fecha_nacimiento'] = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
                }
            }

            // Extra deterministic warnings for user feedback
            if ($type === UserDocumentType::Ine) {
                $vigencia = $fields['vigencia'] ?? '';
                if (filled($vigencia) && is_numeric($vigencia) && (int) $vigencia < now()->year) {
                    $warnings[] = 'La INE muestra vigencia '.$vigencia.', que parece vencida. Por favor verifica que sea el documento correcto.';
                }
            } elseif ($type === UserDocumentType::ComprobantePago) {
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
        } elseif (empty($warnings)) {
            $warnings[] = 'No se pudieron extraer datos automáticamente. Puedes completar el formulario de forma manual.';
        }

        $token = $staging->stage($kind, $path, $result?->toArray(), $warnings);

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
