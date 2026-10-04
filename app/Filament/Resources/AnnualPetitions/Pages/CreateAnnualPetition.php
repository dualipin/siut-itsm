<?php

namespace App\Filament\Resources\AnnualPetitions\Pages;

use App\Filament\Resources\AnnualPetitions\AnnualPetitionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnualPetition extends CreateRecord
{
    protected static string $resource = AnnualPetitionResource::class;

    protected static ?string $title = 'Nuevo Pliego Anual';
}
