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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegistrationController extends Controller
{
    /**
     * Show the public registration page.
     * Redirects to portal dashboard if user is already logged in.
     */
    public function index(): View|RedirectResponse
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
     * Complete the registration, authenticate the user, and return redirect URL.
     */
    public function store(RegisterAgremiadoRequest $request, RegisterAgremiado $action): JsonResponse
    {
        $user = $action->execute($request->validated());

        Auth::login($user);

        return response()->json([
            'redirect' => url('/portal'),
            'message' => '¡Bienvenido al portal SIUT! Tu cuenta ha sido creada exitosamente.',
        ]);
    }
}
