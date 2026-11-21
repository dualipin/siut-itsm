<?php

namespace App\Filament\Resources\TransparencyRecords\Schemas;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TransparencyRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Registro')
                    ->description('Datos generales de identificación y clasificación.')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre / Asunto')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('summary')
                            ->label('Descripción breve')
                            ->rows(3)
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                Select::make('type')
                                    ->label('Categoría')
                                    ->options(TransparencyRecordType::class)
                                    ->required(),
                                Select::make('status')
                                    ->label('Visibilidad')
                                    ->options(TransparencyRecordStatus::class)
                                    ->default(TransparencyRecordStatus::Interno)
                                    ->required(),
                                TextInput::make('reference_number')
                                    ->label('Folio')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                DatePicker::make('document_date')
                                    ->label('Fecha del Documento'),
                                TextInput::make('fiscal_year')
                                    ->label('Año Fiscal')
                                    ->required()
                                    ->default(now()->year)
                                    ->numeric(),
                                TextInput::make('period')
                                    ->label('Periodo'),
                            ]),
                        Textarea::make('observations')
                            ->label('Motivo de acceso interno')
                            ->rows(2)
                            ->columnSpanFull(),
                        Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                    ]),

                Section::make('Documentos Adjuntos')
                    ->description('Sube y administra los archivos digitales correspondientes a este registro.')
                    ->icon(Heroicon::DocumentCheck)
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('documents')
                            ->label('Archivos')
                            ->collection('documents')
                            ->multiple()
                            ->maxSize(15360)
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull()
                            ->getUploadedFileUsing(static function (SpatieMediaLibraryFileUpload $component, string $file): ?array {
                                $record = $component->getRecord();
                                if (! $record instanceof TransparencyRecord) {
                                    return null;
                                }

                                $media = $record->getRelationValue('media')?->firstWhere('uuid', $file)
                                    ?? $record->getMedia('documents')->firstWhere('uuid', $file);

                                if (! $media) {
                                    return null;
                                }

                                return [
                                    'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
                                    'size' => $media->getAttributeValue('size'),
                                    'type' => $media->getAttributeValue('mime_type'),
                                    'url' => route('portal.transparency.documents.show', [
                                        'record' => $record,
                                        'media' => $media,
                                    ]),
                                ];
                            }),
                    ]),
            ]);
    }
}
