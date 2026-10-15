<?php

namespace App\Filament\Resources\AnnualPetitions\Tables;

use App\Actions\GeneratePetitionConsolidatedReport;
use App\Models\AnnualPetition;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnualPetitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('year')
                    ->label('Año')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('deadline')
                    ->label('Fecha Límite')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('petition_requests_count')
                    ->label('Peticiones Recibidas')
                    ->counts('petitionRequests')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('link')
                    ->label('Enlace')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->url(fn (AnnualPetition $record): string => route('petitions.index', $record))
                    ->openUrlInNewTab(),

                Action::make('copy_link')
                    ->label('Copiar')
                    ->icon('heroicon-o-clipboard-document')
                    ->color('gray')
                    ->action(function (AnnualPetition $record, Component $livewire): void {
                        $link = route('petitions.index', $record);

                        $livewire->js("window.navigator.clipboard.writeText('{$link}');");

                        Notification::make()
                            ->title('Enlace copiado al portapapeles')
                            ->success()
                            ->send();
                    }),
                Action::make('download_report')
                    ->label('Reporte')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(function (AnnualPetition $record, GeneratePetitionConsolidatedReport $generateReport): StreamedResponse {
                        $pdf = $generateReport->execute($record);

                        return response()->streamDownload(
                            fn () => print ($pdf->output()),
                            "Reporte-Pliego-Anual-{$record->year}.pdf"
                        );
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('year', 'desc');
    }
}
