<?php

namespace App\Filament\Resources\TransparencyRecords\Pages;

use App\Filament\Resources\TransparencyRecords\TransparencyRecordResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTransparencyRecord extends ViewRecord
{
    protected static string $resource = TransparencyRecordResource::class;

    protected static ?string $title = 'Detalle del Registro de Transparencia';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
