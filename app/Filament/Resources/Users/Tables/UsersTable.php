<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(fn (User $record): ?string => filament()->getUserAvatarUrl($record)),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('surnames')
                    ->label('Apellidos')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->searchable(),
                TextColumn::make('document_status')
                    ->label('Documentación')
                    ->badge()
                    ->state(fn (User $record): string => $record->getOverallDocumentStatus()->getLabel())
                    ->color(fn (User $record): string => $record->getOverallDocumentStatus()->getColor())
                    ->icon(fn (User $record): ?string => $record->getOverallDocumentStatus()->getIcon()),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('curp')
                    ->label('CURP')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->date()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Categoría')
                    ->searchable(),
                TextColumn::make('nss')
                    ->label('Número de Seguridad Social')
                    ->searchable(),
                TextColumn::make('salary')
                    ->label('Salario')
                    ->money('MXN', true)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('hiring_date')
                    ->label('Fecha de Contratación')
                    ->date()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('validateDocuments')
                    ->label('Documentos')
                    ->icon(Heroicon::DocumentCheck)
                    ->color(fn (User $record): string => match ($record->getOverallDocumentStatus()) {
                        DocumentStatus::Valid => 'success',
                        DocumentStatus::Invalid => 'danger',
                        DocumentStatus::Pending => 'warning',
                    })
                    ->modalHeading(fn (User $record): string => 'Expediente de Documentos - '.$record->full_name)
                    ->modalDescription('Revisa los documentos en PDF del agremiado, valida su estatus o ingresa observaciones si requieren corrección.')
                    ->fillForm(function (User $record): array {
                        $data = [];
                        foreach (UserDocumentType::cases() as $type) {
                            $media = $record->getDocumentMedia($type);
                            $data['status_'.$type->value] = $media ? ($media->getCustomProperty('status') ?? DocumentStatus::Pending->value) : 'sin_subir';
                            $data['rejection_'.$type->value] = $media ? $media->getCustomProperty('rejection_reason') : null;
                        }

                        return $data;
                    })
                    ->form(function (User $record): array {
                        $components = [];
                        foreach (UserDocumentType::cases() as $type) {
                            $media = $record->getDocumentMedia($type);
                            $fileInfo = $media
                                ? new HtmlString(
                                    '<div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 border border-gray-200">'.
                                    '<div><span class="font-medium text-gray-900">'.e($media->file_name).'</span> <span class="text-xs text-gray-500">('.number_format($media->size / 1024, 1).' KB)</span></div>'.
                                    '<a href="'.e($media->getUrl()).'" target="_blank" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-primary-700 underline">'.
                                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg> Abrir PDF'.
                                    '</a></div>'
                                )
                                : new HtmlString('<p class="text-sm italic text-gray-500">El usuario aún no ha subido este documento.</p>');

                            $fields = [
                                Placeholder::make('file_info_'.$type->value)
                                    ->label('Archivo PDF')
                                    ->content($fileInfo),
                            ];

                            if ($media) {
                                $fields[] = Grid::make(2)
                                    ->schema([
                                        Select::make('status_'.$type->value)
                                            ->label('Estatus del Documento')
                                            ->options([
                                                DocumentStatus::Pending->value => 'Pendiente de Revisión',
                                                DocumentStatus::Valid->value => 'Válido / Aprobado',
                                                DocumentStatus::Invalid->value => 'Inválido / Observado',
                                            ])
                                            ->required()
                                            ->reactive(),
                                        Textarea::make('rejection_'.$type->value)
                                            ->label('Motivo u Observación de Rechazo')
                                            ->placeholder('Describe qué dato o página debe ser corregido...')
                                            ->visible(fn ($get) => $get('status_'.$type->value) === DocumentStatus::Invalid->value)
                                            ->required(fn ($get) => $get('status_'.$type->value) === DocumentStatus::Invalid->value)
                                            ->rows(2),
                                    ]);
                            }

                            $components[] = Section::make($type->getLabel())
                                ->icon($type->getIcon())
                                ->compact()
                                ->schema($fields);
                        }

                        return $components;
                    })
                    ->extraModalActions([
                        Action::make('approveAllDocuments')
                            ->label('Aprobar Todos los Subidos')
                            ->icon(Heroicon::CheckCircle)
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading('¿Aprobar todos los documentos subidos?')
                            ->modalDescription('Esta acción marcará como Válidos todos los documentos cargados por el agremiado y le enviará la notificación correspondiente.')
                            ->action(function (User $record): void {
                                $approvedCount = 0;
                                foreach (UserDocumentType::cases() as $type) {
                                    $media = $record->getDocumentMedia($type);
                                    if ($media) {
                                        $media->setCustomProperty('status', DocumentStatus::Valid->value);
                                        $media->setCustomProperty('reviewed_by', auth()->id());
                                        $media->setCustomProperty('reviewed_at', now()->toIso8601String());
                                        $media->forgetCustomProperty('rejection_reason');
                                        $media->save();
                                        $approvedCount++;
                                    }
                                }

                                if ($record->areAllDocumentsValid()) {
                                    Notification::make()
                                        ->title('¡Todos tus documentos han sido validados!')
                                        ->body('La administración ha aprobado la totalidad de tu expediente de afiliación. Tu cuenta ahora tiene estatus Válido.')
                                        ->success()
                                        ->sendToDatabase($record);
                                } else {
                                    Notification::make()
                                        ->title('Documentos aprobados')
                                        ->body("La administración ha aprobado tus documentos subidos ({$approvedCount}).")
                                        ->success()
                                        ->sendToDatabase($record);
                                }

                                Notification::make()
                                    ->title('Documentos aprobados')
                                    ->body("Se aprobaron {$approvedCount} documento(s) para {$record->full_name}.")
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->action(function (User $record, array $data): void {
                        $notificationsToSend = [];
                        $anyChanged = false;

                        foreach (UserDocumentType::cases() as $type) {
                            $media = $record->getDocumentMedia($type);
                            if (! $media) {
                                continue;
                            }

                            $oldStatus = $media->getCustomProperty('status') ?? DocumentStatus::Pending->value;
                            $newStatus = $data['status_'.$type->value] ?? $oldStatus;
                            $rejectionReason = $data['rejection_'.$type->value] ?? null;

                            if ($oldStatus !== $newStatus || ($newStatus === DocumentStatus::Invalid->value && $media->getCustomProperty('rejection_reason') !== $rejectionReason)) {
                                $anyChanged = true;
                                $media->setCustomProperty('status', $newStatus);
                                $media->setCustomProperty('reviewed_by', auth()->id());
                                $media->setCustomProperty('reviewed_at', now()->toIso8601String());

                                if ($newStatus === DocumentStatus::Invalid->value) {
                                    $media->setCustomProperty('rejection_reason', $rejectionReason);
                                    $notificationsToSend[] = [
                                        'title' => 'Documento marcado como Inválido',
                                        'body' => "Tu documento '{$type->getLabel()}' fue marcado como inválido. Motivo: {$rejectionReason}. Por favor ingresa a tu perfil para subir un archivo corregido.",
                                        'type' => 'danger',
                                    ];
                                } elseif ($newStatus === DocumentStatus::Valid->value) {
                                    $media->forgetCustomProperty('rejection_reason');
                                    $notificationsToSend[] = [
                                        'title' => 'Documento aprobado',
                                        'body' => "Tu documento '{$type->getLabel()}' ha sido revisado y validado satisfactoriamente.",
                                        'type' => 'success',
                                    ];
                                } else {
                                    $media->forgetCustomProperty('rejection_reason');
                                }

                                $media->save();
                            }
                        }

                        if ($record->areAllDocumentsValid()) {
                            Notification::make()
                                ->title('¡Todos tus documentos han sido validados!')
                                ->body('La administración ha aprobado la totalidad de tu expediente de afiliación. Tu cuenta ahora cuenta con estatus Válido.')
                                ->success()
                                ->sendToDatabase($record);
                        } else {
                            foreach ($notificationsToSend as $notif) {
                                $item = Notification::make()
                                    ->title($notif['title'])
                                    ->body($notif['body']);

                                if ($notif['type'] === 'danger') {
                                    $item->danger();
                                } else {
                                    $item->success();
                                }

                                $item->sendToDatabase($record);
                            }
                        }

                        if ($anyChanged) {
                            Notification::make()
                                ->title('Documentos actualizados')
                                ->body("Se actualizaron los estatus de los documentos para {$record->full_name} y se le notificó.")
                                ->success()
                                ->send();
                        }
                    }),
                Action::make('approveAllDocuments')
                    ->label('Aprobar Todo')
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->visible(fn (User $record): bool => ! $record->areAllDocumentsValid())
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => '¿Aprobar todos los documentos de '.$record->full_name.'?')
                    ->modalDescription('Esta acción marcará como Válidos todos los documentos cargados por el agremiado y le enviará la notificación correspondiente.')
                    ->action(function (User $record): void {
                        $approvedCount = 0;
                        foreach (UserDocumentType::cases() as $type) {
                            $media = $record->getDocumentMedia($type);
                            if ($media) {
                                $media->setCustomProperty('status', DocumentStatus::Valid->value);
                                $media->setCustomProperty('reviewed_by', auth()->id());
                                $media->setCustomProperty('reviewed_at', now()->toIso8601String());
                                $media->forgetCustomProperty('rejection_reason');
                                $media->save();
                                $approvedCount++;
                            }
                        }

                        if ($record->areAllDocumentsValid()) {
                            Notification::make()
                                ->title('¡Todos tus documentos han sido validados!')
                                ->body('La administración ha aprobado la totalidad de tu expediente de afiliación. Tu cuenta ahora tiene estatus Válido.')
                                ->success()
                                ->sendToDatabase($record);
                        } else {
                            Notification::make()
                                ->title('Documentos aprobados')
                                ->body("La administración ha aprobado tus documentos subidos ({$approvedCount}).")
                                ->success()
                                ->sendToDatabase($record);
                        }

                        Notification::make()
                            ->title('Documentos aprobados')
                            ->body("Se aprobaron {$approvedCount} documento(s) para {$record->full_name}.")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
