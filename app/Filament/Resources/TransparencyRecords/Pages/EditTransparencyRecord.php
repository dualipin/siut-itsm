<?php

namespace App\Filament\Resources\TransparencyRecords\Pages;

use App\Filament\Resources\TransparencyRecords\TransparencyRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTransparencyRecord extends EditRecord
{
    protected static string $resource = TransparencyRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
