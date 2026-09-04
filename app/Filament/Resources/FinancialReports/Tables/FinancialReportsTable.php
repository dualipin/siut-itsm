<?php

namespace App\Filament\Resources\FinancialReports\Tables;

use App\Filament\Resources\FinancialReports\FinancialReportResource;
use App\Models\FinancialReport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FinancialReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->label('Año')
                    ->numeric(thousandsSeparator: false)
                    ->sortable(),
                TextColumn::make('creator.full_name')
                    ->label('Subido por')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('open_document')
                    ->label('Ver Documento')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (FinancialReport $record): string => FinancialReportResource::getUrl('document', ['record' => $record]))
                    ->openUrlInNewTab()
                    ->visible(fn (FinancialReport $record): bool => $record->hasMedia('financial_reports')),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
