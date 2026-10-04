<?php

namespace App\Filament\Resources\PetitionRequests\Tables;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PetitionRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('agremiado_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('curp')
                    ->label('CURP')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('annualPetition.year')
                    ->label('Año')
                    ->sortable(),
                TextColumn::make('proposal')
                    ->label('Propuesta')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('media_count')
                    ->label('Adjuntos')
                    ->counts('media')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('annual_petition_id')
                    ->label('Año de la Convocatoria')
                    ->relationship('annualPetition', 'year')
                    ->searchable()
                    ->preload(),
                Filter::make('created_at')
                    ->label('Periodo de Registro')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('hasta')
                            ->label('Hasta')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
