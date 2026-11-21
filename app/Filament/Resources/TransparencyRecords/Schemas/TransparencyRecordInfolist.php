<?php

namespace App\Filament\Resources\TransparencyRecords\Schemas;

use App\Models\TransparencyRecord;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TransparencyRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nombre / Asunto'),
                TextEntry::make('reference_number')
                    ->label('Folio')
                    ->placeholder('-'),
                TextEntry::make('summary')
                    ->label('Descripción breve')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('fiscal_year')
                    ->label('Año Fiscal')
                    ->numeric(thousandsSeparator: false),
                TextEntry::make('period')
                    ->label('Periodo')
                    ->placeholder('-'),
                TextEntry::make('document_date')
                    ->label('Fecha del Documento')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('type')
                    ->label('Categoría')
                    ->badge(),
                TextEntry::make('status')
                    ->label('Visibilidad')
                    ->badge(),
                TextEntry::make('observations')
                    ->label('Motivo de acceso interno')
                    ->placeholder('Ninguna')
                    ->columnSpanFull(),
                TextEntry::make('documents_list')
                    ->label('Documentos Adjuntos')
                    ->state(fn (TransparencyRecord $record): array => $record->getMedia('documents')
                        ->map(fn (Media $media): string => sprintf(
                            '<a href="%s" target="_blank" class="text-primary-600 hover:underline">%s</a>',
                            e(route('portal.transparency.documents.show', ['record' => $record, 'media' => $media])),
                            e($media->file_name)
                        ))
                        ->all())
                    ->html()
                    ->listWithLineBreaks()
                    ->placeholder('Sin documentos adjuntos')
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
