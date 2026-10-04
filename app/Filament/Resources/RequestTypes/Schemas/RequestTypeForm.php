<?php

namespace App\Filament\Resources\RequestTypes\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RequestTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Descripción')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Toggle::make('requires_attachment')
                    ->label('Requiere Evidencia / Archivo Adjunto')
                    ->default(false),
                TextInput::make('max_per_user_per_year')
                    ->label('Máximo por Usuario al Año')
                    ->numeric()
                    ->nullable(),
                Repeater::make('custom_fields')
                    ->label('Campos Dinámicos / Preguntas Adicionales')
                    ->schema([
                        TextInput::make('label')
                            ->label('Pregunta')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                                if (($get('name') ?? '') !== Str::slug($old, '_')) {
                                    return;
                                }
                                $set('name', Str::slug($state, '_'));
                            }),
                        Hidden::make('name')
                            ->required(),
                        Select::make('type')
                            ->label('Tipo de Dato')
                            ->options([
                                'text' => 'Texto Corto',
                                'number' => 'Número',
                                'date' => 'Fecha',
                            ])
                            ->required()
                            ->default('text'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}
