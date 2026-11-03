<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Filament\Resources\Users\Schemas\DocumentReviewInfolist;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
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

    private function getPreviousRecord(): ?User
    {
        /** @var User $current */
        $current = $this->getRecord();

        return User::query()
            ->where(function ($query) use ($current): void {
                $query->where('surnames', '<', $current->surnames)
                    ->orWhere(function ($query) use ($current): void {
                        $query->where('surnames', '=', $current->surnames)
                            ->where('name', '<', $current->name);
                    })
                    ->orWhere(function ($query) use ($current): void {
                        $query->where('surnames', '=', $current->surnames)
                            ->where('name', '=', $current->name)
                            ->where('id', '<', $current->id);
                    });
            })
            ->orderBy('surnames', 'desc')
            ->orderBy('name', 'desc')
            ->orderBy('id', 'desc')
            ->first();
    }

    private function getNextRecord(): ?User
    {
        /** @var User $current */
        $current = $this->getRecord();

        return User::query()
            ->where(function ($query) use ($current): void {
                $query->where('surnames', '>', $current->surnames)
                    ->orWhere(function ($query) use ($current): void {
                        $query->where('surnames', '=', $current->surnames)
                            ->where('name', '>', $current->name);
                    })
                    ->orWhere(function ($query) use ($current): void {
                        $query->where('surnames', '=', $current->surnames)
                            ->where('name', '=', $current->name)
                            ->where('id', '>', $current->id);
                    });
            })
            ->orderBy('surnames')
            ->orderBy('name')
            ->orderBy('id')
            ->first();
    }
}
