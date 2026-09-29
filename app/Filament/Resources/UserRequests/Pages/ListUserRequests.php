<?php

namespace App\Filament\Resources\UserRequests\Pages;

use App\Filament\Resources\UserRequests\UserRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserRequests extends ListRecords
{
    protected static string $resource = UserRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
