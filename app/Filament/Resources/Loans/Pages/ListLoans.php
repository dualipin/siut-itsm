<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Services\LoanCalendarService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        $isWindowOpen = app(LoanCalendarService::class)->isApplicationWindowOpen();

        return [
            Action::make('createLoan')
                ->label('Nueva Solicitud')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->disabled(! $isWindowOpen)
                ->tooltip($isWindowOpen ? null : 'El periodo de recepción de solicitudes concluyó el 30 de noviembre.')
                ->url(fn (): ?string => $isWindowOpen ? ApplyLoan::getUrl() : null),
        ];
    }
}
