<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createLoan')
                ->label('Nueva Solicitud')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->url(fn (): string => ApplyLoan::getUrl()),
        ];
    }
}
