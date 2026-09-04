<?php

namespace App\Filament\Resources\TransparencyRecords\Pages;

use App\Filament\Resources\TransparencyRecords\TransparencyRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransparencyRecords extends ListRecords
{
    protected static string $resource = TransparencyRecordResource::class;

    protected static ?string $title = 'Registros de Transparencia';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
