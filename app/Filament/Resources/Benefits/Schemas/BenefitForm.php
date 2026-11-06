<?php

namespace App\Filament\Resources\Benefits\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class BenefitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre de la Prestación')
                    ->required()
                    ->maxLength(255),

                Select::make('collection_month')
                    ->label('Mes Recurrente de Cobro')
                    ->options([
                        1 => 'Enero',
                        2 => 'Febrero',
                        3 => 'Marzo',
                        4 => 'Abril',
                        5 => 'Mayo',
                        6 => 'Junio',
                        7 => 'Julio',
                        8 => 'Agosto',
                        9 => 'Septiembre',
                        10 => 'Octubre',
                        11 => 'Noviembre',
                        12 => 'Diciembre',
                    ])
                    ->required(),

                Select::make('collection_day')
                    ->label('Día de Cobro')
                    ->options(array_combine(range(1, 31), range(1, 31)))
                    ->required(),

                Select::make('calculation_method')
                    ->label('Método de Cálculo')
                    ->options([
                        'compound_interest' => 'Interés Compuesto (Al vencimiento de la prestación)',
                        'simple_interest' => 'Interés Simple',
                    ])
                    ->default('compound_interest')
                    ->required(),

                Toggle::make('requires_document')
                    ->label('Requiere Comprobante o Evidencia Documental')
                    ->default(true),

                Textarea::make('document_instructions')
                    ->label('Instrucciones Documentales para el Solicitante')
                    ->placeholder('Ej. Subir talón o constancia emitida por el área de Recursos Humanos')
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Disponible en el Catálogo')
                    ->default(true),
            ]);
    }
}
