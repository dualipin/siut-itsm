<?php

namespace App\Filament\Resources\RequestTypes\Pages;

use App\Filament\Resources\RequestTypes\RequestTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRequestType extends EditRecord
{
    protected static string $resource = RequestTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
