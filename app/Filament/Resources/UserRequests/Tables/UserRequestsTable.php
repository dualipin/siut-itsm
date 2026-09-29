<?php

namespace App\Filament\Resources\UserRequests\Tables;

use App\Actions\GenerateUserRequestReceipt;
use App\Models\UserRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UserRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('folio')
                    ->label('Folio')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.fullName')
                    ->label('Agremiado')
                    ->searchable(['name', 'surnames'])
                    ->sortable(),
                TextColumn::make('requestType.name')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('download_receipt')
                    ->label('Descargar Comprobante')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(function (UserRequest $record, GenerateUserRequestReceipt $generateReceipt) {
                        $pdf = $generateReceipt->execute($record);

                        return response()->streamDownload(fn () => print ($pdf->output()), "Comprobante-{$record->folio}.pdf");
                    }),
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make()
                    ->hidden(fn () => auth()->user()?->isAgremiado()),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
}
