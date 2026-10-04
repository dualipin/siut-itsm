<?php

namespace App\Filament\Resources\AnnualPetitions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class AnnualPetitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('year')
                    ->label('Año')
                    ->numeric()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->minValue(2000)
                    ->maxValue(2100)
                    ->columnSpan(1),
                DatePicker::make('deadline')
                    ->label('Fecha Límite')
                    ->required()
                    ->native(false)
                    ->columnSpan(1),
                Repeater::make('convocations')
                    ->relationship('convocations')
                    ->label('Convocatoria y Formato Base')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre descriptivo (opcional)')
                                    ->placeholder('Ej: Convocatoria 2026, Anexo 1, Formato Base')
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                SpatieMediaLibraryFileUpload::make('file')
                                    ->label('Archivo')
                                    ->collection('file')
                                    ->acceptedFileTypes([
                                        'application/pdf',
                                        'image/jpeg',
                                        'image/png',
                                    ])
                                    ->maxSize(10240)
                                    ->downloadable()
                                    ->openable()
                                    ->required()
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->defaultItems(0)
                    ->addActionLabel('Agregar archivo')
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? ($state['file'] ?? 'Nuevo archivo'))
                    ->reorderable('sort_order')
                    ->collapsible()
                    ->columnSpanFull()
                    ->helperText('Agregue uno o más archivos. El nombre descriptivo es opcional; si no se proporciona, se usará el nombre del archivo.'),
            ]);
    }
}
