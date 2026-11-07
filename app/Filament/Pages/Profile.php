<?php

namespace App\Filament\Pages;

use App\Actions\GenerateUserCredentialPdf;
use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Models\User;
use App\Services\DocumentAIService;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Profile extends BaseEditProfile
{
    protected static ?string $title = 'Mi Perfil';

    /** @var array<string, mixed> Staged AI preview results, keyed by document type value. */
    public array $aiPreviews = [];

    public function getHeading(): string|Htmlable
    {
        return 'Mi Perfil';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Administra tus datos personales, credenciales y expediente digital de agremiado.';
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadCredential')
                ->label('Descargar Credencial')
                ->icon(Heroicon::Identification)
                ->color('primary')
                ->visible(fn (): bool => $this->getUser()->areAllDocumentsValid())
                ->action(fn () => app(GenerateUserCredentialPdf::class)->execute($this->getUser())),
        ];
    }

    protected function getNameFormComponent(): Component
    {
        return parent::getNameFormComponent()
            ->label('Nombre(s)');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->label('Correo Electrónico')
            ->disabled(fn (): bool => auth()->user()?->isAgremiado() ?? false)
            ->dehydrated(fn (): bool => ! (auth()->user()?->isAgremiado() ?? false));
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return parent::getCurrentPasswordFormComponent()
            ->label('Contraseña Actual');
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->label('Nueva Contraseña');
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return parent::getPasswordConfirmationFormComponent()
            ->label('Confirmar Nueva Contraseña');
    }

    public function form(Schema $schema): Schema
    {
        $user = $this->getUser();

        return $schema
            ->model($user)
            ->components([
                Section::make('Fotografía y Datos Personales')
                    ->description('Actualiza tu información personal, datos de contacto y compensación.')
                    ->icon(Heroicon::User)
                    ->schema([
                        FileUpload::make('photo_path')
                            ->label('Foto de Perfil')
                            ->avatar()
                            ->disk('public')
                            ->visibility('public')
                            ->directory('profile-photos')
                            ->getUploadedFileUsing(static function (FileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                                $data = $component->getUploadedFile($file, $storedFileNames);

                                if ($data && ! empty($data['url'])) {
                                    $parsed = parse_url($data['url']);
                                    $data['url'] = ($parsed['path'] ?? '').(isset($parsed['query']) ? '?'.$parsed['query'] : '');
                                }

                                return $data;
                            })
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                $this->getNameFormComponent(),
                                TextInput::make('surnames')
                                    ->label('Apellidos')
                                    ->required()
                                    ->maxLength(255),
                                $this->getEmailFormComponent(),
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(20),
                                DatePicker::make('birth_date')
                                    ->label('Fecha de Nacimiento')
                                    ->maxDate(now()->subYears(18))
                                    ->disabled(fn (): bool => auth()->user()?->isAgremiado() ?? false)
                                    ->dehydrated(fn (): bool => ! (auth()->user()?->isAgremiado() ?? false)),
                                TextInput::make('salary')
                                    ->label('Salario')
                                    ->numeric()
                                    ->prefix('$')
                                    ->disabled(fn (): bool => auth()->user()?->isAgremiado() ?? false)
                                    ->dehydrated(fn (): bool => ! (auth()->user()?->isAgremiado() ?? false)),
                            ]),
                        Textarea::make('address')
                            ->label('Dirección')
                            ->rows(3)
                            ->cols(50)
                            ->columnSpanFull(),
                    ]),

                Section::make('Información Laboral e Identificación')
                    ->description('Datos de registro institucional y laboral (solo lectura).')
                    ->icon(Heroicon::Identification)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('curp_display')
                                    ->label('CURP')
                                    ->state(fn (?Model $record): string => $record instanceof User && filled($record->curp) ? $record->curp : 'No registrado'),
                                TextEntry::make('role_display')
                                    ->label('Rol en el Sistema')
                                    ->state(fn (?Model $record): string => $record instanceof User && $record->role ? ($record->role->getLabel() ?? 'Sin rol') : 'Sin rol asignado'),
                                TextEntry::make('category_display')
                                    ->label('Categoría')
                                    ->state(fn (?Model $record): string => $record instanceof User && filled($record->category) ? $record->category : 'Sin categoría asignada'),
                                TextEntry::make('nss_display')
                                    ->label('Número de Seguridad Social (NSS)')
                                    ->state(fn (?Model $record): string => $record instanceof User && filled($record->nss) ? $record->nss : 'No registrado'),
                                TextEntry::make('hiring_date_display')
                                    ->label('Fecha de Contratación')
                                    ->state(fn (?Model $record): string => $record instanceof User && $record->hiring_date ? $record->hiring_date->format('d/m/Y') : 'No registrada'),
                                TextEntry::make('status_display')
                                    ->label('Estado de Cuenta')
                                    ->state(fn (?Model $record): string => $record instanceof User ? ($record->is_active ? 'Activo' : 'Inactivo') : '-'),
                                TextEntry::make('document_status_display')
                                    ->label('Estatus de Validación de Documentos')
                                    ->badge()
                                    ->state(fn (?Model $record): string => $record instanceof User ? $record->getOverallDocumentStatus()->getLabel() : '-')
                                    ->color(fn (?Model $record): string => $record instanceof User ? $record->getOverallDocumentStatus()->getColor() : 'gray'),
                            ]),
                    ]),

                Section::make('Documentos de Afiliación')
                    ->description('Sube y gestiona tus documentos obligatorios. INE y comprobante de pago también aceptan imagen (JPG, PNG, WebP). Los demás documentos solo en PDF.')
                    ->icon(Heroicon::DocumentCheck)
                    ->schema([
                        $this->buildDocumentUploadField(UserDocumentType::Afiliacion, '1. Comprobante de Afiliación'),
                        $this->buildDocumentUploadField(UserDocumentType::ComprobanteDomicilio, '2. Comprobante de Domicilio'),
                        $this->buildDocumentUploadField(UserDocumentType::Ine, '3. INE / Identificación Oficial'),
                        $this->buildDocumentUploadField(UserDocumentType::ComprobantePago, '4. Comprobante de Pago'),
                        $this->buildDocumentUploadField(UserDocumentType::Curp, '5. CURP (Documento Oficial)'),
                    ]),

                Section::make('Seguridad')
                    ->description('Modifica tu contraseña de acceso si lo requieres.')
                    ->icon(Heroicon::Key)
                    ->schema([
                        $this->getCurrentPasswordFormComponent(),
                        Grid::make(2)
                            ->schema([
                                $this->getPasswordFormComponent(),
                                $this->getPasswordConfirmationFormComponent(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Build a standardized Spatie Media file upload component for an affiliation document.
     * INE and ComprobantePago also accept images; others remain PDF-only.
     */
    protected function buildDocumentUploadField(UserDocumentType $type, string $label): SpatieMediaLibraryFileUpload
    {
        $acceptsImages = in_array($type, User::imageAcceptingDocumentTypes(), true);

        $acceptedMimes = $acceptsImages
            ? ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']
            : ['application/pdf'];

        $helperBase = $acceptsImages
            ? 'Sube un archivo PDF o imagen (JPG, PNG, WebP).'
            : 'Sube un archivo PDF válido.';

        $field = SpatieMediaLibraryFileUpload::make($type->value)
            ->label($label)
            ->collection($type->value)
            ->visibility('private')
            ->acceptedFileTypes($acceptedMimes)
            ->maxSize(10240)
            ->downloadable()
            ->openable()
            ->hintAction(
                Action::make('open_'.$type->value)
                    ->label('Abrir documento')
                    ->icon(Heroicon::ArrowTopRightOnSquare)
                    ->visible(fn (?Model $record): bool => $record instanceof User && $record->getDocumentMedia($type) !== null)
                    ->url(fn (?Model $record): ?string => $record instanceof User ? route('portal.users.documents.show', ['user' => $record, 'type' => $type->value]) : null)
                    ->openUrlInNewTab()
            )
            ->disabled(fn (?Model $record): bool => $record instanceof User && $record->getDocumentStatus($type) === DocumentStatus::Valid)
            ->hint(function (?Model $record) use ($type): ?string {
                if (! $record instanceof User) {
                    return null;
                }

                $status = $record->getDocumentStatus($type);
                if ($status === DocumentStatus::Valid) {
                    return '✓ Validado y Aprobado';
                }
                if ($status === DocumentStatus::Invalid) {
                    return '⚠ Inválido - Requiere corrección';
                }
                if ($status === DocumentStatus::Processing) {
                    return '🔍 Verificando documento...';
                }
                if ($status === DocumentStatus::Pending) {
                    return '⏳ Pendiente de revisión';
                }

                return 'Pendiente de subir';
            })
            ->hintColor(function (?Model $record) use ($type): string {
                if (! $record instanceof User) {
                    return 'gray';
                }

                $status = $record->getDocumentStatus($type);
                if ($status === DocumentStatus::Valid) {
                    return 'success';
                }
                if ($status === DocumentStatus::Invalid) {
                    return 'danger';
                }
                if ($status === DocumentStatus::Processing) {
                    return 'info';
                }
                if ($status === DocumentStatus::Pending) {
                    return 'warning';
                }

                return 'gray';
            })
            ->helperText(function (?Model $record) use ($type, $helperBase): ?string {
                if (! $record instanceof User) {
                    return $helperBase;
                }

                $status = $record->getDocumentStatus($type);
                if ($status === DocumentStatus::Valid) {
                    return 'Este documento ha sido validado satisfactoriamente por la administración. No requiere cambios.';
                }
                if ($status === DocumentStatus::Invalid) {
                    $reason = $record->getDocumentRejectionReason($type);

                    return 'Observación de la administración: "'.($reason ?: 'Documento no válido').'". Por favor selecciona un nuevo archivo para reemplazarlo.';
                }
                if ($status === DocumentStatus::Processing) {
                    return 'Estamos verificando tu documento. Recibirás una notificación si es rechazado.';
                }
                if ($status === DocumentStatus::Pending) {
                    return 'Documento en proceso de revisión por parte de la administración.';
                }

                return $helperBase;
            });

        // For INE and nómina, run AI sync analysis when a new file is chosen
        if ($acceptsImages) {
            $field->live()->afterStateUpdated(function ($state, ?Model $record) use ($type): void {
                if (! $record instanceof User || ! filled($state)) {
                    return;
                }

                // Stage the AI preview so it can be persisted when the form is saved
                $this->stageAiPreviewForProfile($record, $type);
            });
        }

        return $field;
    }

    /**
     * Run AI analysis synchronously and stage the result for persistence at save time.
     * Never throws — any failure is logged and ignored.
     */
    private function stageAiPreviewForProfile(User $user, UserDocumentType $type): void
    {
        $media = $user->getFirstMedia($type->value);

        if (! $media) {
            return;
        }

        // Skip if already analyzed (avoid double-billing)
        if ($media->hasCustomProperty('ai_processed_at')) {
            return;
        }

        $bytes = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

        if (! is_string($bytes) || $bytes === '') {
            return;
        }

        $throttleKey = 'user:'.($user->id ?? 'unknown');

        /** @var DocumentAIService $service */
        $service = app(DocumentAIService::class);

        ['result' => $result, 'warnings' => $warnings] = $service->review(
            $bytes,
            (string) $media->mime_type,
            $type,
            $throttleKey,
        );

        if (! empty($warnings)) {
            Log::info('Profile AI warnings staged.', ['type' => $type->value, 'warnings' => $warnings]);
        }

        // Only store a result; never overwrite a previously staged preview with null
        if ($result !== null) {
            $this->aiPreviews[$type->value] = $result->toArray();
        }
    }

    /**
     * Handle updating user profile, saving media relationships, and persisting AI metadata.
     * No longer dispatches ProcessDocumentAIJob — analysis is synchronous.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record instanceof User && $record->isAgremiado()) {
            unset($data['email'], $data['birth_date'], $data['salary']);
        }

        $record = parent::handleRecordUpdate($record, $data);
        $this->form->model($record)->saveRelationships();

        $updatedAny = false;

        foreach (UserDocumentType::cases() as $type) {
            $media = $record->getFirstMedia($type->value);

            if (! $media) {
                continue;
            }

            $status = $media->getCustomProperty('status');

            // Only process freshly uploaded or previously invalid documents
            if ($status && $status !== DocumentStatus::Invalid->value) {
                continue;
            }

            // Set Pending status and clear rejection reason
            $media->setCustomProperty('status', DocumentStatus::Pending->value);
            $media->forgetCustomProperty('rejection_reason');

            // Persist AI metadata staged during file selection (no second call)
            if (isset($this->aiPreviews[$type->value])) {
                $preview = $this->aiPreviews[$type->value];

                if ($preview !== null) {
                    $media->setCustomProperty('extracted_data', $preview);
                    $media->setCustomProperty('ai_flagged', ! ($preview['is_valid_document'] ?? true));
                    $media->setCustomProperty('ai_processed_at', now()->toIso8601String());

                    if (! empty($preview['ai_flags'])) {
                        $media->setCustomProperty('ai_flags', $preview['ai_flags']);
                    }
                } else {
                    // AI was unavailable: mark for manual review
                    $media->setCustomProperty('ai_processed_at', now()->toIso8601String());
                    $media->setCustomProperty('ai_error', 'Análisis automático no disponible; revisión manual pendiente.');
                }
            }

            $media->save();
            $updatedAny = true;
        }

        if ($updatedAny) {
            Notification::make()
                ->title('Documentación actualizada')
                ->body('Tus documentos han sido actualizados y enviados a revisión por parte de la administración.')
                ->info()
                ->send();
        }

        // Reset staged previews after save
        $this->aiPreviews = [];

        return $record;
    }
}
