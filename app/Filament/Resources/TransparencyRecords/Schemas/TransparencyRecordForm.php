<?php

namespace App\Filament\Resources\TransparencyRecords\Schemas;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use Filament\Forms\Components\Hidden;
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
                    ->label('Nombre del Registro')
                    ->required(),
                Textarea::make('summary')
                    ->label('Resumen')
                    ->columnSpanFull(),
                TextInput::make('fiscal_year')
                    ->label('Año Fiscal')
                    ->required()
                    ->numeric(),
                TextInput::make('period')
                    ->label('Periodo')
                    ->required(),
                Select::make('type')
                    ->label('Tipo')
                    ->options(TransparencyRecordType::class)
                    ->required(),
                Select::make('status')
                    ->label('Estado')
                    ->options(TransparencyRecordStatus::class)
                    ->default(TransparencyRecordStatus::Borrador)
                    ->required(),
                Textarea::make('observations')
                    ->label('Observaciones')
                    ->columnSpanFull(),
                Hidden::make('created_by')
                    ->default(fn () => auth()->id()),
            ]);
    }
}
