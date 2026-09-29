<?php

namespace App\Filament\Resources\RequestTypes\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

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
                \Filament\Forms\Components\Repeater::make('custom_fields')
                    ->label('Campos Dinámicos / Preguntas Adicionales')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('label')
                            ->label('Pregunta')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (\Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set, ?string $old, ?string $state) {
                                if (($get('name') ?? '') !== \Illuminate\Support\Str::slug($old, '_')) {
                                    return;
                                }
                                $set('name', \Illuminate\Support\Str::slug($state, '_'));
                            }),
                        \Filament\Forms\Components\Hidden::make('name')
                            ->required(),
                        \Filament\Forms\Components\Select::make('type')
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
