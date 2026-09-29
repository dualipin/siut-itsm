<?php

namespace App\Filament\Resources\UserRequests\Schemas;

use App\Enums\RequestStatus;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class UserRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('request_type_id')
                    ->label('Tipo de Solicitud')
                    ->relationship('requestType', 'name')
                    ->required()
                    ->live(),
                Textarea::make('reason')
                    ->label('Motivo / Descripción')
                    ->required()
                    ->maxLength(65535)
                    ->columnSpanFull(),
                \Filament\Schemas\Components\Section::make('Información Requerida')
                    ->schema(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        $requestTypeId = $get('request_type_id');
                        if (! $requestTypeId) {
                            return [];
                        }

                        $type = \App\Models\RequestType::find($requestTypeId);
                        if (! $type || empty($type->custom_fields)) {
                            return [];
                        }

                        $fields = [];
                        foreach ($type->custom_fields as $customField) {
                            $name = $customField['name'];
                            $label = $customField['label'];
                            $inputType = $customField['type'] ?? 'text';

                            $component = match ($inputType) {
                                'number' => \Filament\Forms\Components\TextInput::make("additional_data.{$name}")->numeric(),
                                'date' => \Filament\Forms\Components\DatePicker::make("additional_data.{$name}"),
                                default => \Filament\Forms\Components\TextInput::make("additional_data.{$name}"),
                            };

                            $fields[] = $component
                                ->label($label)
                                ->required();
                        }

                        return $fields;
                    })
                    ->columns(2)
                    ->hidden(function (\Filament\Schemas\Components\Utilities\Get $get) {
                        $requestTypeId = $get('request_type_id');
                        if (! $requestTypeId) {
                            return true;
                        }
                        $type = \App\Models\RequestType::find($requestTypeId);
                        return ! $type || empty($type->custom_fields);
                    }),
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label('Archivos Adjuntos (Evidencia)')
                    ->collection('attachments')
                    ->multiple()
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Estado')
                    ->options(RequestStatus::class)
                    ->default(RequestStatus::Pending)
                    ->required()
                    ->hidden(fn () => auth()->user()?->isAgremiado()),
                Textarea::make('resolution_notes')
                    ->label('Notas de Resolución')
                    ->maxLength(65535)
                    ->columnSpanFull()
                    ->hidden(fn () => auth()->user()?->isAgremiado()),
            ]);
    }
}
