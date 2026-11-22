<?php

namespace App\Filament\Resources\TransparencyRecords\Pages;

use App\Enums\TransparencyRecordStatus;
use App\Filament\Resources\TransparencyRecords\TransparencyRecordResource;
use App\Models\TransparencyRecord;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTransparencyRecord extends EditRecord
{
    protected static string $resource = TransparencyRecordResource::class;

    protected static ?string $title = 'Editar Registro de Transparencia';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TransparencyRecord::sanitizeAttributes($data);
    }

    protected function afterSave(): void
    {
        /** @var TransparencyRecord $record */
        $record = $this->getRecord();
        $record->unsetRelation('media');

        if ($record->isPublico() && ! $record->hasAttachedDocuments()) {
            $record->unpublish();
            $this->data['status'] = TransparencyRecordStatus::Interno->value;

            Notification::make()
                ->warning()
                ->title('Visibilidad cambiada a Consulta Interna')
                ->body('Al eliminar todos los archivos adjuntos, el registro pasó automáticamente a Consulta Interna.')
                ->send();
        }
    }
}
