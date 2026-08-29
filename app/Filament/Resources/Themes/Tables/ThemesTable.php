<?php

namespace App\Filament\Resources\Themes\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Table;

class ThemesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color_primary')->label('Primario'),
                ColorColumn::make('color_secondary')->label('Secundario'),
                ColorColumn::make('color_neutral')->label('Neutro'),
                ColorColumn::make('color_success')->label('Exitoso'),
                ColorColumn::make('color_warning')->label('Advertencia'),
                ColorColumn::make('color_info')->label('Información'),
                ColorColumn::make('color_error')->label('Error'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->paginated(false);
    }
}
