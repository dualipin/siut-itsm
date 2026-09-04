<?php

namespace App\Filament\Resources\TransparencyRecords\Pages;

use App\Filament\Resources\TransparencyRecords\TransparencyRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransparencyRecord extends CreateRecord
{
    protected static string $resource = TransparencyRecordResource::class;

    protected static ?string $title = 'Nuevo Registro de Transparencia';
}
