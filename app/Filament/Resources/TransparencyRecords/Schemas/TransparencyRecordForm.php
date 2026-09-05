<?php

namespace App\Filament\Resources\TransparencyRecords\Schemas;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyDocument;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                            ->label('Nombre del Registro')
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('summary')
                            ->label('Resumen')
                            ->rows(3)
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
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
                            ]),
                        Textarea::make('observations')
                            ->label('Observaciones')
                            ->rows(2)
                            ->columnSpanFull(),
                        Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                    ]),

                Section::make('Documentos Adjuntos')
                    ->description('Sube y administra los archivos y expedientes digitales correspondientes a este registro.')
                    ->icon(Heroicon::DocumentCheck)
                    ->schema([
                        Repeater::make('documents')
                            ->relationship('documents')
                            ->label('Documentos del Registro')
                            ->addActionLabel('Agregar Documento')
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? $state['name'] : 'Nuevo Documento')
                            ->defaultItems(0)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nombre del Documento')
                                            ->placeholder('Ej. Contrato Colectivo 2026, Informe Trimestral')
                                            ->required()
                                            ->maxLength(255),
                                        DatePicker::make('published_at')
                                            ->label('Fecha de Publicación / Vigencia')
                                            ->default(now()),
                                    ]),
                                Toggle::make('is_public')
                                    ->label('¿Es Público?')
                                    ->helperText('Determina si este documento estará disponible para descarga en el portal público')
                                    ->default(true),
                                SpatieMediaLibraryFileUpload::make('file')
                                    ->label('Archivo')
                                    ->collection('file')
                                    ->downloadable()
                                    ->openable()
                                    ->required()
                                    ->columnSpanFull()
                                    ->hintAction(
                                        Action::make('open_file')
                                            ->label('Abrir Documento')
                                            ->icon(Heroicon::ArrowTopRightOnSquare)
                                            ->visible(fn (?TransparencyDocument $record): bool => $record !== null && $record->hasMedia('file'))
                                            ->url(fn (?TransparencyDocument $record): ?string => $record !== null && $record->hasMedia('file') ? route('portal.transparency.documents.show', $record) : null)
                                            ->openUrlInNewTab()
                                    )
                                    ->getUploadedFileUsing(static function (SpatieMediaLibraryFileUpload $component, string $file): ?array {
                                        $record = $component->getRecord();
                                        if (! $record instanceof TransparencyDocument) {
                                            return null;
                                        }

                                        $media = $record->getRelationValue('media')?->firstWhere('uuid', $file);

                                        if (! $media) {
                                            return null;
                                        }

                                        return [
                                            'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
                                            'size' => $media->getAttributeValue('size'),
                                            'type' => $media->getAttributeValue('mime_type'),
                                            'url' => route('portal.transparency.documents.show', ['document' => $record]),
                                        ];
                                    }),
                                Hidden::make('uploaded_by')
                                    ->default(fn () => auth()->id()),
                            ]),
                    ]),
            ]);
    }
}
