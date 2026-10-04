<?php

namespace App\Filament\Resources\PetitionRequests\Pages;

use App\Filament\Resources\PetitionRequests\PetitionRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPetitionRequest extends ViewRecord
{
    protected static string $resource = PetitionRequestResource::class;

    protected static ?string $title = 'Detalle de la Petición';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
