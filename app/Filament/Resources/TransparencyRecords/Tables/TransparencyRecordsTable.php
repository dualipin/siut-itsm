<?php

namespace App\Filament\Resources\TransparencyRecords\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TransparencyRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->label('Folio')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('fiscal_year')
                    ->label('Año Fiscal')
                    ->numeric(thousandsSeparator: false)
                    ->sortable(),
                TextColumn::make('period')
                    ->label('Periodo')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('document_date')
                    ->label('Fecha del Documento')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Categoría')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Visibilidad')
                    ->badge()
                    ->searchable(),
                TextColumn::make('media_count')
                    ->counts('media')
                    ->label('Documentos')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Creado por')
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
                TrashedFilter::make(),
            ])
            ->recordActions([
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
