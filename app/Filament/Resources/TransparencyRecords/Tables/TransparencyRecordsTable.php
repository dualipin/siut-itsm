<?php

namespace App\Filament\Resources\TransparencyRecords\Tables;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransparencyRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['creator', 'media']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Folio')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nombre / Asunto')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Categoría')
                    ->badge(),
                TextColumn::make('date_or_period')
                    ->label('Fecha / Periodo')
                    ->state(function (TransparencyRecord $record): string {
                        if ($record->type?->usesPeriod()) {
                            return filled($record->period)
                                ? "{$record->fiscal_year} · {$record->period}"
                                : (string) $record->fiscal_year;
                        }

                        return $record->document_date?->translatedFormat('d M Y')
                            ?? (string) $record->fiscal_year;
                    }),
                TextColumn::make('status')
                    ->label('Visibilidad')
                    ->badge(),
                TextColumn::make('media_count')
                    ->counts('media')
                    ->label('Archivos')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('creator.name')
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
                TextColumn::make('deleted_at')
                    ->label('Fecha de Eliminación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Categoría')
                    ->options(TransparencyRecordType::class),
                SelectFilter::make('status')
                    ->label('Visibilidad')
                    ->options(TransparencyRecordStatus::class),
                SelectFilter::make('fiscal_year')
                    ->label('Año')
                    ->options(fn (): array => TransparencyRecord::query()
                        ->whereNotNull('fiscal_year')
                        ->distinct()
                        ->orderByDesc('fiscal_year')
                        ->pluck('fiscal_year')
                        ->mapWithKeys(fn (mixed $year): array => [(string) $year => (string) $year])
                        ->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('toggle_visibility')
                    ->label(fn (TransparencyRecord $record): string => $record->isPublico()
                        ? 'Pasar a Consulta Interna'
                        : 'Hacer Público')
                    ->icon(fn (TransparencyRecord $record): Heroicon => $record->isPublico()
                        ? Heroicon::LockClosed
                        : Heroicon::GlobeAlt)
                    ->color(fn (TransparencyRecord $record): string => $record->isPublico()
                        ? 'warning'
                        : 'success')
                    ->authorize('update')
                    ->visible(fn (TransparencyRecord $record): bool => ! $record->trashed())
                    ->action(function (TransparencyRecord $record): void {
                        if ($record->isPublico()) {
                            $record->unpublish();

                            Notification::make()
                                ->title('Registro marcado como Consulta Interna')
                                ->body('Sus archivos quedaron bloqueados para descarga pública.')
                                ->success()
                                ->send();

                            return;
                        }

                        if (! $record->publish()) {
                            Notification::make()
                                ->title('No se puede hacer público')
                                ->body('Adjunta al menos un archivo antes de cambiar la visibilidad a Público.')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Registro marcado como Público')
                            ->body('Cualquier visitante podrá descargar sus archivos adjuntos.')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
