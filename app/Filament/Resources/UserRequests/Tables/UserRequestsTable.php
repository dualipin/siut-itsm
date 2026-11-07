<?php

namespace App\Filament\Resources\UserRequests\Tables;

use App\Actions\GenerateUserRequestReceipt;
use App\Enums\RequestStatus;
use App\Models\UserRequest;
use App\Models\UserRequestHistory;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
                TrashedFilter::make()
                    ->visible(fn (): bool => auth()->user()?->isLeaderOrAdmin() ?? false),
            ])
            ->recordActions([
                Action::make('download_receipt')
                    ->label('Descargar Comprobante')
                    ->icon(Heroicon::ArrowDownTray)
                    ->color('gray')
                    ->action(function (UserRequest $record, GenerateUserRequestReceipt $generateReceipt) {
                        $pdf = $generateReceipt->execute($record);

                        return response()->streamDownload(fn () => print ($pdf->output()), "Comprobante-{$record->folio}.pdf");
                    }),
                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('¿Cancelar solicitud?')
                    ->modalDescription('Esta acción cancelará tu solicitud de forma definitiva.')
                    ->authorize('cancel')
                    ->action(function (UserRequest $record): void {
                        $record->update(['status' => RequestStatus::Cancelled]);

                        UserRequestHistory::create([
                            'user_request_id' => $record->id,
                            'user_id' => auth()->id(),
                            'status' => RequestStatus::Cancelled,
                            'comment' => 'Solicitud cancelada por el agremiado.',
                        ]);

                        Notification::make()
                            ->title('Solicitud cancelada')
                            ->body('Tu solicitud ha sido cancelada exitosamente.')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ])->visible(fn (): bool => auth()->user()?->isLeaderOrAdmin() ?? false),
            ]);
    }
}
