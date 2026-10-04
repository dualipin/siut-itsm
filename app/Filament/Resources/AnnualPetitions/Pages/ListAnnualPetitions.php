<?php

namespace App\Filament\Resources\AnnualPetitions\Pages;

use App\Filament\Resources\AnnualPetitions\AnnualPetitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnnualPetitions extends ListRecords
{
    protected static string $resource = AnnualPetitionResource::class;

    protected static ?string $title = 'Pliegos Anuales';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
