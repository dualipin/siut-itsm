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
                TextEntry::make('name'),
                TextEntry::make('summary')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('fiscal_year')
                    ->numeric(),
                TextEntry::make('period'),
                TextEntry::make('observations')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('type')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (TransparencyRecord $record): bool => $record->trashed()),
            ]);
    }
}
