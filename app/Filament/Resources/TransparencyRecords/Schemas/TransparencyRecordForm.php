<?php

namespace App\Filament\Resources\TransparencyRecords\Schemas;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class TransparencyRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información del Registro')
                    ->description('Selecciona la categoría documental para capturar únicamente los datos necesarios.')
                    ->icon(Heroicon::DocumentText)
                    ->schema([
                        Select::make('type')
                            ->label('Categoría')
                            ->options(TransparencyRecordType::class)
                            ->required()
                            ->live()
                            ->afterStateUpdated(static function (Set $set, mixed $state): void {
                                $type = self::resolveType($state);

                                if (! ($type?->usesPeriod() ?? false)) {
                                    $set('period', null);
                                }

                                if (! ($type?->usesDocumentDate() ?? false)) {
                                    $set('document_date', null);
                                }

                                if (! ($type?->usesFolio() ?? false)) {
                                    $set('reference_number', null);
                                }
                            })
                            ->helperText(static fn (Get $get): string => $get->enum('type', TransparencyRecordType::class)?->getDescription()
                                ?? 'Financiero: balances y reportes; Oficios: correspondencia; Actas: asambleas; Normativo y Legal: estatutos y convenios; Otros: archivo general.')
                            ->rule(static function (Get $get, ?TransparencyRecord $record): Closure {
                                return static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $type = self::resolveType($value);

                                    if (! $type || ! ($record instanceof TransparencyRecord)) {
                                        return;
                                    }

                                    $allowedExtensions = $type->allowedExtensions();
                                    $incompatibleFiles = [];

                                    foreach ($get->array('documents') as $file) {
                                        if (! is_string($file)) {
                                            continue;
                                        }

                                        $media = $record->getRelationValue('media')?->firstWhere('uuid', $file)
                                            ?? $record->getMedia('documents')->firstWhere('uuid', $file);

                                        if (! $media) {
                                            continue;
                                        }

                                        $extension = strtolower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION));

                                        if ($extension !== '' && ! in_array($extension, $allowedExtensions, true)) {
                                            $incompatibleFiles[] = $media->file_name;
                                        }
                                    }

                                    if ($incompatibleFiles !== []) {
                                        $fail(sprintf(
                                            'No se puede cambiar a la categoría %s mientras existan archivos incompatibles adjuntos (%s). Elimínalos o mantén la categoría Financiero.',
                                            $type->getLabel(),
                                            implode(', ', $incompatibleFiles),
                                        ));
                                    }
                                };
                            })
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Nombre / Asunto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('summary')
                            ->label('Descripción breve')
                            ->rows(3)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('fiscal_year')
                                    ->label('Año Fiscal')
                                    ->required()
                                    ->default(now()->year)
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1990)
                                    ->maxValue(now()->year + 1)
                                    ->visible(static fn (Get $get): bool => $get->enum('type', TransparencyRecordType::class)?->usesPeriod() ?? false),

                                Select::make('period')
                                    ->label('Periodo')
                                    ->options(TransparencyRecordType::periodOptions())
                                    ->required()
                                    ->searchable()
                                    ->visible(static fn (Get $get): bool => $get->enum('type', TransparencyRecordType::class)?->usesPeriod() ?? false),

                                DatePicker::make('document_date')
                                    ->label('Fecha del Documento')
                                    ->required()
                                    ->native(false)
                                    ->helperText('Fecha original del documento. Si solo se conoce el año, selecciona el 1 de enero de ese año.')
                                    ->visible(static fn (Get $get): bool => $get->enum('type', TransparencyRecordType::class)?->usesDocumentDate() ?? false),

                                TextInput::make('reference_number')
                                    ->label('No. de Oficio / Folio')
                                    ->trim()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->validationMessages([
                                        'unique' => 'Este número de oficio o folio ya está registrado en otro documento.',
                                    ])
                                    ->visible(static fn (Get $get): bool => $get->enum('type', TransparencyRecordType::class)?->usesFolio() ?? false),
                            ]),

                        Hidden::make('created_by')
                            ->default(static fn (): ?int => auth()->id())
                            ->dehydrated(static fn (?TransparencyRecord $record): bool => ! ($record?->exists ?? false)),
                    ]),

                Section::make('Documentos Adjuntos')
                    ->description('Sube uno o más archivos digitalizados (máximo 15 MB por archivo).')
                    ->icon(Heroicon::DocumentCheck)
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('documents')
                            ->label('Archivos')
                            ->collection('documents')
                            ->multiple()
                            ->maxSize(15360)
                            ->acceptedFileTypes(static fn (Get $get): array => (
                                $get->enum('type', TransparencyRecordType::class) ?? TransparencyRecordType::Financiero
                            )->allowedMimeTypes())
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull()
                            ->validationMessages([
                                'max' => 'Cada archivo adjunto no debe superar los 15 MB.',
                                'mimetypes' => 'El formato del archivo no está permitido para la categoría seleccionada.',
                                'mimes' => 'El formato del archivo no está permitido para la categoría seleccionada.',
                            ])
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

                Section::make('Visibilidad en el Portal')
                    ->description('Define quién puede descargar los archivos adjuntos.')
                    ->icon(Heroicon::Eye)
                    ->schema([
                        ToggleButtons::make('status')
                            ->label('Visibilidad')
                            ->options(TransparencyRecordStatus::class)
                            ->icons([
                                TransparencyRecordStatus::Interno->value => Heroicon::LockClosed,
                                TransparencyRecordStatus::Publico->value => Heroicon::GlobeAlt,
                            ])
                            ->inline()
                            ->default(TransparencyRecordStatus::Interno)
                            ->required()
                            ->live()
                            ->afterStateUpdated(static function (Set $set, mixed $state): void {
                                $status = self::resolveStatus($state);

                                if ($status !== TransparencyRecordStatus::Interno) {
                                    $set('observations', null);
                                }
                            })
                            ->helperText(static fn (Get $get): string => $get->enum('status', TransparencyRecordStatus::class)?->getDescription()
                                ?? TransparencyRecordStatus::Interno->getDescription())
                            ->rule(static function (Get $get, ?TransparencyRecord $record): Closure {
                                return static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $status = self::resolveStatus($value);

                                    if ($status !== TransparencyRecordStatus::Publico) {
                                        return;
                                    }

                                    if (filled($get->array('documents'))) {
                                        return;
                                    }

                                    if ($record instanceof TransparencyRecord
                                        && $record->exists
                                        && $record->isPublic()
                                        && $record->getMedia('documents')->isNotEmpty()) {
                                        return;
                                    }

                                    $fail('Para publicar como Público debes adjuntar al menos un archivo. Si aún no cuentas con él, guárdalo en Consulta Interna.');
                                };
                            })
                            ->columnSpanFull(),

                        Textarea::make('observations')
                            ->label('Motivo de acceso interno')
                            ->rows(2)
                            ->placeholder('Ej. Contiene datos personales de agremiados o correspondencia interna.')
                            ->visible(static fn (Get $get): bool => $get->enum('status', TransparencyRecordStatus::class) === TransparencyRecordStatus::Interno)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Clear out-of-context attributes and derive fiscal_year from document_date outside Financiero.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeData(array $data): array
    {
        $type = self::resolveType($data['type'] ?? null);
        $status = self::resolveStatus($data['status'] ?? null);

        if ($type?->usesPeriod()) {
            $data['document_date'] = null;
        } else {
            $data['period'] = null;

            if (filled($data['document_date'] ?? null)) {
                $data['fiscal_year'] = Carbon::parse($data['document_date'])->year;
            }
        }

        if (! ($type?->usesFolio() ?? false)) {
            $data['reference_number'] = null;
        }

        if ($status !== TransparencyRecordStatus::Interno) {
            $data['observations'] = null;
        }

        return $data;
    }

    private static function resolveType(mixed $value): ?TransparencyRecordType
    {
        if ($value instanceof TransparencyRecordType) {
            return $value;
        }

        return filled($value) ? TransparencyRecordType::tryFrom((string) $value) : null;
    }

    private static function resolveStatus(mixed $value): ?TransparencyRecordStatus
    {
        if ($value instanceof TransparencyRecordStatus) {
            return $value;
        }

        return filled($value) ? TransparencyRecordStatus::tryFrom((string) $value) : null;
    }
}
