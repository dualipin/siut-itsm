<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\GenerateUserCredentialPdf;
use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Exceptions\DocumentAIException;
use App\Filament\Resources\Users\Schemas\DocumentReviewInfolist;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\DocumentAIService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReviewDocuments extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Revisión de Documentos';

    public string $activeDocumentType = 'afiliacion';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $requestedDoc = (string) request()->query('document', '');
        if (UserDocumentType::tryFrom($requestedDoc) !== null) {
            $this->activeDocumentType = $requestedDoc;
        } else {
            $this->activeDocumentType = UserDocumentType::cases()[0]->value;
        }
    }

    // ── Infolist ──────────────────────────────────────────────────────────────

    public function infolist(Schema $schema): Schema
    {
        return DocumentReviewInfolist::configure($schema);
    }

    // ── Header actions ────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previousRecord')
                ->label('Anterior')
                ->icon(Heroicon::ChevronLeft)
                ->color('gray')
                ->url(fn (): ?string => ($prev = $this->getPreviousRecord())
                    ? UserResource::getUrl('review-documents', ['record' => $prev])
                    : null
                )
                ->disabled(fn (): bool => $this->getPreviousRecord() === null),

            Action::make('nextRecord')
                ->label('Siguiente')
                ->icon(Heroicon::ChevronRight)
                ->iconPosition('after')
                ->color('gray')
                ->url(fn (): ?string => ($next = $this->getNextRecord())
                    ? UserResource::getUrl('review-documents', ['record' => $next])
                    : null
                )
                ->disabled(fn (): bool => $this->getNextRecord() === null),

            EditAction::make()
                ->label('Editar perfil'),

            Action::make('downloadCredential')
                ->label('Descargar Credencial')
                ->icon(Heroicon::Identification)
                ->color('primary')
                ->action(fn () => app(GenerateUserCredentialPdf::class)->execute($this->getRecord())),

            Action::make('approveAllDocuments')
                ->label('Aprobar todos')
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->visible(fn (): bool => ! $this->getRecord()->areAllDocumentsValid())
                ->requiresConfirmation()
                ->modalHeading(fn (): string => '¿Aprobar todos los documentos de '.$this->getRecord()->full_name.'?')
                ->modalDescription('Esta acción marcará como Válidos todos los documentos cargados por el agremiado y le enviará la notificación correspondiente.')
                ->action(function (): void {
                    /** @var User $user */
                    $user = $this->getRecord();
                    $approvedCount = 0;

                    foreach (UserDocumentType::cases() as $type) {
                        $media = $user->getDocumentMedia($type);
                        if (! $media) {
                            continue;
                        }

                        $media->setCustomProperty('status', DocumentStatus::Valid->value);
                        $media->setCustomProperty('reviewed_by', auth()->id());
                        $media->setCustomProperty('reviewed_at', now()->toIso8601String());
                        $media->forgetCustomProperty('rejection_reason');
                        $media->save();
                        $approvedCount++;
                    }

                    Notification::make()
                        ->title($user->areAllDocumentsValid() ? '¡Todos los documentos han sido validados!' : 'Documentos aprobados')
                        ->body($user->areAllDocumentsValid()
                            ? 'La administración ha aprobado la totalidad del expediente de afiliación.'
                            : "Se aprobaron {$approvedCount} documento(s) subidos."
                        )
                        ->success()
                        ->sendToDatabase($user);

                    Notification::make()
                        ->title('Documentos aprobados')
                        ->body("Se aprobaron {$approvedCount} documento(s) para {$user->full_name}.")
                        ->success()
                        ->send();

                    $this->refreshFormData([]);
                }),
        ];
    }

    // ── Document Review Actions ──────────────────────────────────────────────

    public function analyzeWithAiAction(): Action
    {
        return Action::make('analyzeWithAi')
            ->label('Analizar con IA')
            ->icon('heroicon-m-cpu-chip')
            ->color('info')
            ->modalHeading(fn (array $arguments): string => 'Analizar con IA: '.(UserDocumentType::tryFrom($arguments['type'] ?? $this->activeDocumentType)?->getLabel() ?? ''))
            ->modalDescription('Se enviará el documento al servicio de IA para extraer datos. Esta operación consume créditos del proveedor.')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $typeKey = $arguments['type'] ?? $this->activeDocumentType;
                $type = UserDocumentType::from($typeKey);

                /** @var User $user */
                $user = $this->getRecord();
                $media = $user->getDocumentMedia($type);

                if (! $media) {
                    Notification::make()
                        ->title('Sin documento')
                        ->body('El documento no ha sido subido aún.')
                        ->warning()
                        ->send();

                    return;
                }

                if (! config('services.document_ai.enabled')) {
                    Notification::make()
                        ->title('IA deshabilitada')
                        ->body('El servicio de IA no está habilitado en esta instalación.')
                        ->warning()
                        ->send();

                    return;
                }

                $bytes = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

                if (! is_string($bytes) || $bytes === '') {
                    Notification::make()
                        ->title('Error')
                        ->body('No se pudo leer el archivo del documento.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    /** @var DocumentAIService $service */
                    $service = app(DocumentAIService::class);
                    $result = $service->analyzeBytes($bytes, (string) $media->mime_type, $type);

                    $media->setCustomProperty('extracted_data', $result->toArray());
                    $media->setCustomProperty('ai_processed_at', now()->toIso8601String());
                    $media->setCustomProperty('ai_flagged', ! $result->isValidDocument);

                    if (! empty($result->aiFlags)) {
                        $media->setCustomProperty('ai_flags', $result->aiFlags);
                    }

                    $media->save();

                    $message = $result->isValidDocument
                        ? 'El documento fue analizado correctamente por la IA.'
                        : 'La IA marcó el documento como posiblemente inválido. Confianza: '.round($result->confidenceScore * 100).'%.';

                    if (filled($result->observations)) {
                        $message .= ' Observación: '.$result->observations;
                    }

                    Notification::make()
                        ->title('Análisis completado')
                        ->body($message)
                        ->success()
                        ->send();

                    Log::info('ReviewDocuments: análisis manual con IA completado.', [
                        'media_id' => $media->id,
                        'type' => $type->value,
                        'valid' => $result->isValidDocument,
                        'confidence' => $result->confidenceScore,
                    ]);
                } catch (DocumentAIException $e) {
                    Log::warning('ReviewDocuments: análisis manual con IA falló.', [
                        'media_id' => $media->id,
                        'type' => $type->value,
                        'error' => Str::limit($e->getMessage(), 200),
                    ]);

                    Notification::make()
                        ->title('Error de IA')
                        ->body('No se pudo analizar el documento: '.Str::limit($e->getMessage(), 150))
                        ->danger()
                        ->send();
                }

                $this->refreshFormData([]);
            });
    }

    public function approveDocumentAction(): Action
    {
        return Action::make('approveDocument')
            ->label('Autorizar')
            ->icon('heroicon-m-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Autorizar documento: '.(UserDocumentType::tryFrom($arguments['type'] ?? $this->activeDocumentType)?->getLabel() ?? ''))
            ->modalDescription('Esta acción marcará el documento como Válido y notificará al usuario.')
            ->action(function (array $arguments): void {
                $typeKey = $arguments['type'] ?? $this->activeDocumentType;
                $type = UserDocumentType::from($typeKey);
                /** @var User $user */
                $user = $this->getRecord();
                $media = $user->getDocumentMedia($type);

                if (! $media) {
                    Notification::make()
                        ->title('Error')
                        ->body('El documento no ha sido subido aún.')
                        ->danger()
                        ->send();

                    return;
                }

                $media->setCustomProperty('status', DocumentStatus::Valid->value);
                $media->setCustomProperty('reviewed_by', auth()->id());
                $media->setCustomProperty('reviewed_at', now()->toIso8601String());
                $media->forgetCustomProperty('rejection_reason');
                $media->save();

                Notification::make()
                    ->title('Documento aprobado')
                    ->body("Tu documento '{$type->getLabel()}' ha sido revisado y validado satisfactoriamente.")
                    ->success()
                    ->sendToDatabase($user);

                Notification::make()
                    ->title('Documento autorizado')
                    ->body("{$type->getLabel()} marcado como válido para {$user->full_name}.")
                    ->success()
                    ->send();
            });
    }

    public function rejectDocumentAction(): Action
    {
        return Action::make('rejectDocument')
            ->label('Rechazar')
            ->icon('heroicon-m-x-circle')
            ->color('danger')
            ->modalHeading(fn (array $arguments): string => 'Rechazar documento: '.(UserDocumentType::tryFrom($arguments['type'] ?? $this->activeDocumentType)?->getLabel() ?? ''))
            ->form([
                Textarea::make('rejection_reason')
                    ->label('Motivo u observación de rechazo')
                    ->placeholder('Describe qué dato o página debe ser corregido...')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, array $arguments): void {
                $typeKey = $arguments['type'] ?? $this->activeDocumentType;
                $type = UserDocumentType::from($typeKey);
                /** @var User $user */
                $user = $this->getRecord();
                $media = $user->getDocumentMedia($type);

                if (! $media) {
                    Notification::make()
                        ->title('Error')
                        ->body('El documento no ha sido subido aún.')
                        ->danger()
                        ->send();

                    return;
                }

                $media->setCustomProperty('status', DocumentStatus::Invalid->value);
                $media->setCustomProperty('reviewed_by', auth()->id());
                $media->setCustomProperty('reviewed_at', now()->toIso8601String());
                $media->setCustomProperty('rejection_reason', $data['rejection_reason']);
                $media->save();

                Notification::make()
                    ->title('Documento marcado como Inválido')
                    ->body("Tu documento '{$type->getLabel()}' fue marcado como inválido. Motivo: {$data['rejection_reason']}. Por favor ingresa a tu perfil para subir un archivo corregido.")
                    ->danger()
                    ->sendToDatabase($user);

                Notification::make()
                    ->title('Documento rechazado')
                    ->body("{$type->getLabel()} marcado como inválido para {$user->full_name}.")
                    ->danger()
                    ->send();
            });
    }

    public function replaceDocumentAction(): Action
    {
        return Action::make('replaceDocument')
            ->label('Reemplazar archivo')
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            ->modalHeading(fn (array $arguments): string => 'Subir o reemplazar archivo: '.(UserDocumentType::tryFrom($arguments['type'] ?? $this->activeDocumentType)?->getLabel() ?? ''))
            ->modalDescription('Sube el archivo PDF correspondiente. Se guardará en el expediente y el estado se establecerá a Pendiente.')
            ->form(fn (array $arguments): array => [
                FileUpload::make('document_file')
                    ->label(UserDocumentType::tryFrom($arguments['type'] ?? $this->activeDocumentType)?->getLabel() ?? 'Archivo PDF')
                    ->disk('local')
                    ->directory('temp-uploads')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $typeKey = $arguments['type'] ?? $this->activeDocumentType;
                $type = UserDocumentType::from($typeKey);
                /** @var User $user */
                $user = $this->getRecord();

                $user->addMediaFromDisk($data['document_file'], 'local')
                    ->withCustomProperties([
                        'status' => DocumentStatus::Pending->value,
                        'replaced_by' => auth()->id(),
                        'replaced_at' => now()->toIso8601String(),
                    ])
                    ->toMediaCollection($type->value);

                $user->unsetRelation('media');

                Notification::make()
                    ->title('Archivo guardado')
                    ->body("{$type->getLabel()} ha sido actualizado correctamente.")
                    ->success()
                    ->send();
            });
    }

    public function setDocumentValuesAction(): Action
    {
        return Action::make('setDocumentValues')
            ->label('Ingresar datos del documento')
            ->icon('heroicon-m-pencil-square')
            ->color('info')
            ->modalHeading(function (array $arguments): string {
                $type = UserDocumentType::tryFrom($arguments['type'] ?? $this->activeDocumentType);
                $label = $type ? ' — '.$type->getLabel() : '';

                return 'Datos encontrados en el documento'.$label;
            })
            ->modalDescription('Ingresa los valores visibles en el documento para comparar con el perfil del usuario.')
            ->form($this->buildDocumentValuesForm(...))
            ->fillForm(function (array $arguments): array {
                $type = UserDocumentType::tryFrom($arguments['type'] ?? $this->activeDocumentType) ?? UserDocumentType::cases()[0];

                return $this->fillDocumentValuesForm($type);
            })
            ->action($this->saveDocumentValues(...));
    }

    // ── Document values form helpers ─────────────────────────────────────────

    /** @return array<Component> */
    private function buildDocumentValuesForm(): array
    {
        $fields = [
            'doc_name' => 'Nombre(s)',
            'doc_surnames' => 'Apellidos',
            'doc_curp' => 'CURP',
            'doc_email' => 'Correo',
            'doc_phone' => 'Teléfono',
            'doc_address' => 'Dirección',
            'doc_nss' => 'NSS',
            'doc_category' => 'Categoría',
        ];

        $documentTypeOptions = collect(UserDocumentType::cases())
            ->mapWithKeys(fn (UserDocumentType $t): array => [$t->value => $t->getLabel()])
            ->all();

        $components = [
            Select::make('document_type')
                ->label('Documento a revisar')
                ->options($documentTypeOptions)
                ->required(),
        ];

        foreach ($fields as $prop => $label) {
            $components[] = TextInput::make($prop)
                ->label($label)
                ->placeholder('Valor encontrado en el documento...');
        }

        return $components;
    }

    /** @return array<string, mixed> */
    private function fillDocumentValuesForm(?UserDocumentType $type = null): array
    {
        $type ??= UserDocumentType::tryFrom($this->activeDocumentType) ?? UserDocumentType::cases()[0];
        $media = $this->getRecord()->getDocumentMedia($type);

        $data = ['document_type' => $type->value];

        foreach (['doc_name', 'doc_surnames', 'doc_curp', 'doc_email', 'doc_phone', 'doc_address', 'doc_nss', 'doc_category'] as $prop) {
            $data[$prop] = $media?->getCustomProperty($prop);
        }

        return $data;
    }

    private function saveDocumentValues(array $data): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $type = UserDocumentType::from($data['document_type']);
        $media = $user->getDocumentMedia($type);

        if ($media === null) {
            Notification::make()
                ->title('Documento sin subir')
                ->body("El documento {$type->getLabel()} aún no ha sido cargado por el usuario. Primero debes cargar el archivo.")
                ->warning()
                ->send();

            return;
        }

        $props = [
            'doc_name', 'doc_surnames', 'doc_curp', 'doc_email',
            'doc_phone', 'doc_address', 'doc_nss', 'doc_category',
        ];

        foreach ($props as $prop) {
            if (isset($data[$prop]) && $data[$prop] !== '') {
                $media->setCustomProperty($prop, $data[$prop]);
            } else {
                $media->forgetCustomProperty($prop);
            }
        }

        $media->save();

        Notification::make()
            ->title('Datos del documento guardados')
            ->body("Los valores del documento {$type->getLabel()} han sido guardados para cotejo.")
            ->success()
            ->send();
    }

    // ── Navigation helpers ────────────────────────────────────────────────────

    private ?User $cachedPreviousRecord = null;

    private bool $hasCheckedPreviousRecord = false;

    private ?User $cachedNextRecord = null;

    private bool $hasCheckedNextRecord = false;

    private function getPreviousRecord(): ?User
    {
        if ($this->hasCheckedPreviousRecord) {
            return $this->cachedPreviousRecord;
        }

        $this->hasCheckedPreviousRecord = true;

        /** @var User $current */
        $current = $this->getRecord();
        $surnames = (string) ($current->surnames ?? '');
        $name = (string) ($current->name ?? '');
        $id = $current->getKey();

        return $this->cachedPreviousRecord = User::query()
            ->where(function ($query) use ($name, $surnames, $id): void {
                $query->where('name', '<', $name)
                    ->orWhere(function ($query) use ($name, $surnames): void {
                        $query->where('name', '=', $name)
                            ->whereRaw("COALESCE(surnames, '') < ?", [$surnames]);
                    })
                    ->orWhere(function ($query) use ($name, $surnames, $id): void {
                        $query->where('name', '=', $name)
                            ->whereRaw("COALESCE(surnames, '') = ?", [$surnames])
                            ->where('id', '<', $id);
                    });
            })
            ->orderBy('name', 'desc')
            ->orderByRaw("COALESCE(surnames, '') DESC")
            ->orderBy('id', 'desc')
            ->first();
    }

    private function getNextRecord(): ?User
    {
        if ($this->hasCheckedNextRecord) {
            return $this->cachedNextRecord;
        }

        $this->hasCheckedNextRecord = true;

        /** @var User $current */
        $current = $this->getRecord();
        $name = (string) ($current->name ?? '');
        $surnames = (string) ($current->surnames ?? '');
        $id = $current->getKey();

        return $this->cachedNextRecord = User::query()
            ->where(function ($query) use ($name, $surnames, $id): void {
                $query->where('name', '>', $name)
                    ->orWhere(function ($query) use ($name, $surnames): void {
                        $query->where('name', '=', $name)
                            ->whereRaw("COALESCE(surnames, '') > ?", [$surnames]);
                    })
                    ->orWhere(function ($query) use ($name, $surnames, $id): void {
                        $query->where('name', '=', $name)
                            ->whereRaw("COALESCE(surnames, '') = ?", [$surnames])
                            ->where('id', '>', $id);
                    });
            })
            ->orderBy('name')
            ->orderByRaw("COALESCE(surnames, '') ASC")
            ->orderBy('id')
            ->first();
    }
}
