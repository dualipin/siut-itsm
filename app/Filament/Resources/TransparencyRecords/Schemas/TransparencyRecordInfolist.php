<?php

namespace App\Filament\Resources\TransparencyRecords\Schemas;

use App\Models\TransparencyRecord;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TransparencyRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Registro')
                    ->icon(Heroicon::DocumentText)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre / Asunto')
                            ->columnSpanFull(),
                        TextEntry::make('type')
                            ->label('Categoría')
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Visibilidad')
                            ->badge(),
                        TextEntry::make('summary')
                            ->label('Descripción breve')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('fiscal_year')
                            ->label('Año Fiscal')
                            ->numeric(thousandsSeparator: false)
                            ->visible(static fn (TransparencyRecord $record): bool => $record->type?->usesPeriod() ?? false),
                        TextEntry::make('period')
                            ->label('Periodo')
                            ->placeholder('-')
                            ->visible(static fn (TransparencyRecord $record): bool => $record->type?->usesPeriod() ?? false),
                        TextEntry::make('document_date')
                            ->label('Fecha del Documento')
                            ->date()
                            ->placeholder('-')
                            ->visible(static fn (TransparencyRecord $record): bool => $record->type?->usesDocumentDate() ?? false),
                        TextEntry::make('reference_number')
                            ->label('No. de Oficio / Folio')
                            ->placeholder('-')
                            ->visible(static fn (TransparencyRecord $record): bool => $record->type?->usesFolio() ?? false),
                        TextEntry::make('observations')
                            ->label('Motivo de acceso interno')
                            ->placeholder('Sin observaciones registradas')
                            ->visible(static fn (TransparencyRecord $record): bool => $record->isInterno())
                            ->columnSpanFull(),
                    ]),

                Section::make('Documentos Adjuntos')
                    ->icon(Heroicon::DocumentCheck)
                    ->schema([
                        TextEntry::make('documents_list')
                            ->label('Archivos')
                            ->state(static fn (TransparencyRecord $record): array => $record->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION)
                                ->map(static fn (Media $media): string => sprintf(
                                    '<span>%s</span> <span class="text-gray-500">(%s)</span> — <a href="%s" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline font-medium">Abrir</a> · <a href="%s" class="text-primary-600 hover:underline font-medium">Descargar</a>',
                                    e($media->file_name),
                                    e($media->human_readable_size),
                                    e(route('portal.transparency.documents.show', ['record' => $record, 'media' => $media])),
                                    e(route('portal.transparency.documents.show', ['record' => $record, 'media' => $media, 'download' => 1])),
                                ))
                                ->all())
                            ->html()
                            ->listWithLineBreaks()
                            ->placeholder('Sin documentos adjuntos')
                            ->columnSpanFull(),
                    ]),

                Section::make('Auditoría')
                    ->columns(3)
                    ->schema([
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
                            ->visible(static fn (TransparencyRecord $record): bool => $record->trashed()),
                    ]),
            ]);
    }
}
