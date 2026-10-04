<?php

namespace App\Filament\Resources\AnnualPetitions\Pages;

use App\Filament\Resources\AnnualPetitions\AnnualPetitionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnnualPetition extends EditRecord
{
    protected static string $resource = AnnualPetitionResource::class;

    protected static ?string $title = 'Editar Pliego Anual';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
