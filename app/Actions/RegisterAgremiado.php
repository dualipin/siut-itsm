<?php

namespace App\Actions;

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RegistrationStaging;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RegisterAgremiado
{
    public function __construct(
        protected RegistrationStaging $staging
    ) {}

    /**
     * Create an Agremiado user, attach their documents with AI metadata, and clean up temporary files.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $ineEntry = $this->staging->resolve((string) ($data['ine_token'] ?? ''), 'ine');
            $nominaEntry = $this->staging->resolve((string) ($data['nomina_token'] ?? ''), 'nomina');
            $photoEntry = $this->staging->resolve((string) ($data['photo_token'] ?? ''), 'photo');

            // Move photo from local temporary storage to public disk
            $photoDestination = null;
            if ($photoEntry && Storage::disk('local')->exists($photoEntry['path'])) {
                $ext = pathinfo($photoEntry['path'], PATHINFO_EXTENSION) ?: 'jpg';
                $photoDestination = 'profile-photos/'.Str::random(40).'.'.$ext;
                Storage::disk('public')->put(
                    $photoDestination,
                    Storage::disk('local')->get($photoEntry['path'])
                );
            }

            $userData = [
                'name' => trim((string) $data['name']),
                'surnames' => trim((string) $data['surnames']),
                'email' => strtolower(trim((string) $data['email'])),
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                'birth_date' => $data['birth_date'],
                'curp' => mb_strtoupper(trim((string) $data['curp'])),
                'nss' => filled($data['nss'] ?? null) ? trim((string) $data['nss']) : null,
                'category' => filled($data['category'] ?? null) ? trim((string) $data['category']) : null,
                'salary' => filled($data['salary'] ?? null) ? $data['salary'] : null,
                'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
                'hiring_date' => $this->hiringDate($nominaEntry),
                'photo_path' => $photoDestination,
                'password' => Hash::make((string) $data['password']),
                'role' => UserRole::Agremiado,
                'is_active' => true,
            ];

            /** @var User $user */
            $user = User::create($userData);

            // Save INE document as Pending media with AI metadata
            if ($ineEntry) {
                $this->attachDocument($user, UserDocumentType::Ine, $ineEntry);
            }

            // Save Nómina document as Pending media with AI metadata
            if ($nominaEntry) {
                $this->attachDocument($user, UserDocumentType::ComprobantePago, $nominaEntry);
            }

            // Send welcome database notification
            Notification::make()
                ->title('¡Bienvenido al portal SIUT!')
                ->body('Tu cuenta de Agremiado ha sido creada. Tus documentos INE y nómina están en revisión. Para completar tu expediente, sube los 3 documentos restantes (Afiliación, Comprobante de Domicilio y CURP) desde "Mi Perfil".')
                ->info()
                ->sendToDatabase($user);

            // Clean up temporary files on disk
            $pathsToDelete = array_filter([
                $ineEntry['path'] ?? null,
                $nominaEntry['path'] ?? null,
                $photoEntry['path'] ?? null,
            ]);

            if (! empty($pathsToDelete)) {
                Storage::disk('local')->delete($pathsToDelete);
            }

            // Clear session staging
            $this->staging->forgetAll();

            return $user;
        });
    }

    /**
     * Start of the labour relationship as printed on the payslip (server-side staged data, not client input).
     *
     * @param  ?array<string, mixed>  $nominaEntry
     */
    protected function hiringDate(?array $nominaEntry): ?string
    {
        $date = (string) data_get($nominaEntry, 'ai.extracted_fields.fecha_ingreso', '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false && $date <= now()->toDateString() ? $date : null;
    }

    /**
     * Attach a staged document to the user's media collection.
     *
     * @param  array{token: string, kind: string, path: string, ai: ?array<string, mixed>, warnings: list<string>, exp: int}  $stagedEntry
     */
    protected function attachDocument(User $user, UserDocumentType $type, array $stagedEntry): void
    {
        $path = $stagedEntry['path'];
        $fullPath = Storage::disk('local')->path($path);

        if (! file_exists($fullPath)) {
            Log::warning('RegisterAgremiado: archivo temporal no encontrado.', [
                'type' => $type->value,
                'path' => $path,
            ]);

            return;
        }

        $customProperties = [
            'status' => DocumentStatus::Pending->value,
            'ai_processed_at' => now()->toIso8601String(),
        ];

        $aiPreview = $stagedEntry['ai'] ?? null;

        if ($aiPreview !== null) {
            $customProperties['extracted_data'] = $aiPreview;
            $customProperties['ai_flagged'] = ! ($aiPreview['is_valid_document'] ?? true);

            if (! empty($aiPreview['ai_flags'])) {
                $customProperties['ai_flags'] = $aiPreview['ai_flags'];
            }
        } else {
            $customProperties['ai_error'] = 'Documento no analizado automáticamente; pendiente de revisión manual.';
        }

        try {
            $user->addMedia($fullPath)
                ->withCustomProperties($customProperties)
                ->toMediaCollection($type->value);
        } catch (\Exception $e) {
            Log::error('RegisterAgremiado: error al guardar documento en media library.', [
                'type' => $type->value,
                'user_id' => $user->id,
                'error' => Str::limit($e->getMessage(), 200),
            ]);
        }
    }
}
