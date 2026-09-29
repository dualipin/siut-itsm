<?php

namespace App\Filament\Resources\UserRequests\Pages;

use App\Filament\Resources\UserRequests\UserRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserRequest extends ViewRecord
{
    protected static string $resource = UserRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
