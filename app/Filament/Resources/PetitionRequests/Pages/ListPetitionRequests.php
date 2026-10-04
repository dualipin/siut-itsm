<?php

namespace App\Filament\Resources\PetitionRequests\Pages;

use App\Filament\Resources\PetitionRequests\PetitionRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListPetitionRequests extends ListRecords
{
    protected static string $resource = PetitionRequestResource::class;

    protected static ?string $title = 'Peticiones de Pliego';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
