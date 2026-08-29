<?php

namespace App\Filament\Resources\TransparencyRecords\Schemas;

use App\Models\TransparencyRecord;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TransparencyRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nombre'),
                TextEntry::make('summary')
                    ->label('Resumen')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('fiscal_year')
                    ->label('Año Fiscal')
                    ->numeric(thousandsSeparator: false),
                TextEntry::make('period')->label('Periodo'),
                TextEntry::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextEntry::make('status')
                    ->label('Estado')
                    ->badge(),
                TextEntry::make('observations')
                    ->label('Observaciones')
                    ->placeholder('Ninguna')
                    ->columnSpanFull(),
                TextEntry::make('creator.full_name')
                    ->label('Creado por')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Creado el')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->label('Actualizado el')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->label('Eliminado el')
                    ->dateTime()
                    ->visible(fn (TransparencyRecord $record): bool => $record->trashed()),
            ]);
    }
}
