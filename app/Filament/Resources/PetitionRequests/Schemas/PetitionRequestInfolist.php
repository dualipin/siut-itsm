<?php

namespace App\Filament\Resources\PetitionRequests\Schemas;

use App\Models\PetitionRequest;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PetitionRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Agremiado')
                    ->schema([
                        TextEntry::make('agremiado_name')
                            ->label('Nombre Completo')
                            ->placeholder('—'),
                        TextEntry::make('curp')
                            ->label('CURP'),
                        TextEntry::make('annualPetition.year')
                            ->label('Año de la Convocatoria'),
                        TextEntry::make('annualPetition.deadline')
                            ->label('Fecha Límite')
                            ->date('d/m/Y')
                            ->placeholder('—'),
                    ])
                    ->columns(4),
                Section::make('Propuesta')
                    ->schema([
                        TextEntry::make('proposal')
                            ->label('Descripción de la Propuesta')
                            ->prose()
                            ->columnSpanFull(),
                    ]),
                Section::make('Adjuntos')
                    ->schema([
                        RepeatableEntry::make('media')
                            ->label('Archivos Adjuntos')
                            ->state(fn (PetitionRequest $record): array => $record->getMedia('proposal_files')->all())
                            ->schema([
                                TextEntry::make('file_name')
                                    ->label('Archivo')
                                    ->url(fn (Media $record): string => $record->getUrl())
                                    ->openUrlInNewTab()
                                    ->icon(Heroicon::ArrowTopRightOnSquare),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Auditoría')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Fecha de Creación')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Fecha de Actualización')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
