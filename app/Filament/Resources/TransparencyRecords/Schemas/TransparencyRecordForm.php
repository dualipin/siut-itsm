<?php

namespace App\Filament\Resources\TransparencyRecords\Schemas;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransparencyRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('summary')
                    ->columnSpanFull(),
                TextInput::make('fiscal_year')
                    ->required()
                    ->numeric(),
                TextInput::make('period')
                    ->required(),
                Textarea::make('observations')
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(TransparencyRecordType::class)
                    ->required(),
                Select::make('status')
                    ->options(TransparencyRecordStatus::class)
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
