<?php

namespace App\Filament\Resources\FinancialReports\Schemas;

use App\Filament\Resources\FinancialReports\FinancialReportResource;
use App\Models\FinancialReport;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FinancialReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('year')
                    ->label('Año'),
                TextEntry::make('creator.full_name')
                    ->label('Subido por')
                    ->placeholder('-'),
                TextEntry::make('file')
                    ->label('Documento')
                    ->state(fn (FinancialReport $record): string => $record->getFirstMedia('financial_reports')?->file_name ?? 'Sin documento')
                    ->url(fn (FinancialReport $record): ?string => $record->hasMedia('financial_reports') ? FinancialReportResource::getUrl('document', ['record' => $record]) : null)
                    ->openUrlInNewTab()
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color(fn (FinancialReport $record): ?string => $record->hasMedia('financial_reports') ? 'primary' : 'gray')
                    ->helperText(fn (FinancialReport $record): ?string => $record->getFirstMedia('financial_reports')?->human_readable_size),
                TextEntry::make('created_at')
                    ->label('Creado el')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Actualizado el')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
