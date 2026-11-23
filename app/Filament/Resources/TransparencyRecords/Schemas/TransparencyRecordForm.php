<?php

namespace App\Filament\Resources\TransparencyRecords\Schemas;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use App\Models\User;
use App\Services\TransparencyDocumentAIService;
use App\Support\TransparencyAiMetadataResult;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TransparencyRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Documentos Adjuntos')
                    ->key('documents-section', isInheritable: false)
                    ->description('Sube uno o más archivos digitalizados (máximo 15 MB por archivo).')
                    ->icon(Heroicon::DocumentCheck)
                    ->headerActions([
                        Action::make('autofill_with_ai')
                            ->label('Autocompletar con IA')
                            ->icon(Heroicon::Sparkles)
                            ->color('primary')
                            ->outlined()
                            ->size(Size::Small)
                            ->authorize(static fn (): bool => self::isActiveDirectiva())
                            ->visible(static fn (Get $get, string $operation): bool => in_array($operation, ['create', 'edit'], true)
                                && self::isActiveDirectiva()
                                && app(TransparencyDocumentAIService::class)->isEnabled()
                                && filled($get->array('documents')))
                            ->requiresConfirmation(static fn (Get $get): bool => self::hasFilledMetadataFields($get))
                            ->modalIcon(static fn (Get $get): ?Heroicon => self::hasFilledMetadataFields($get)
                                ? Heroicon::OutlinedExclamationTriangle
                                : null)
                            ->modalIconColor(static fn (Get $get): ?string => self::hasFilledMetadataFields($get)
                                ? 'warning'
                                : null)
                            ->modalHeading(static fn (Get $get, ?TransparencyRecord $record): ?string => self::resolveAutofillModalCopy($get, $record)['heading'])
                            ->modalDescription(static fn (Get $get, ?TransparencyRecord $record): ?string => self::resolveAutofillModalCopy($get, $record)['description'])
                            ->modalSubmitActionLabel(static fn (Get $get, ?TransparencyRecord $record): ?string => self::resolveAutofillModalCopy($get, $record)['submitLabel'])
                            ->schema(static function (Get $get, ?TransparencyRecord $record): array {
                                $options = self::resolveDocumentOptions($get->array('documents'), $record);

                                if (count($options) < 2) {
                                    return [];
                                }

                                return [
                                    Select::make('selected_document')
                                        ->label('Documento a analizar')
                                        ->options($options)
                                        ->default(array_key_first($options))
                                        ->required(),
                                ];
                            })
                            ->action(static function (array $data, Get $get, Set $set, ?TransparencyRecord $record): void {
                                if (! self::isActiveDirectiva()) {
                                    return;
                                }

                                $selectedKey = isset($data['selected_document']) ? (string) $data['selected_document'] : null;
                                $document = self::resolveSelectedDocument($get->array('documents'), $record, $selectedKey);

                                self::runAiAnalysis($document, $get, $set, overwrite: true);
                            }),
                    ])
                    ->schema([
                        Hidden::make('ai_auto_triggered')
                            ->default(false)
                            ->dehydrated(false),

                        SpatieMediaLibraryFileUpload::make('documents')
                            ->label('Archivos')
                            ->collection(TransparencyRecord::DOCUMENTS_COLLECTION)
                            ->multiple()
                            ->live()
                            ->afterStateUpdated(static function (Get $get, Set $set, ?TransparencyRecord $record, string $operation): void {
                                if ($operation !== 'create'
                                    || ! self::isActiveDirectiva()
                                    || ! app(TransparencyDocumentAIService::class)->isEnabled()
                                    || (bool) $get('ai_auto_triggered')) {
                                    return;
                                }

                                $documentsState = $get->array('documents');
                                if (blank($documentsState)) {
                                    return;
                                }

                                $firstDocument = self::resolveFirstDocument($documentsState, $record);
                                if ($firstDocument === null) {
                                    return;
                                }

                                $set('ai_auto_triggered', true);

                                if (! self::hasAnyEmptyMetadataField($get)) {
                                    return;
                                }

                                self::runAiAnalysis($firstDocument, $get, $set, overwrite: false);
                            })
                            ->maxSize(15360)
                            ->acceptedFileTypes(static fn (Get $get): array => $get->enum('type', TransparencyRecordType::class)?->allowedMimeTypes()
                                ?? TransparencyRecordType::allAllowedMimeTypes())
                            ->rule(static function (Get $get): string {
                                $extensions = $get->enum('type', TransparencyRecordType::class)?->allowedExtensions()
                                    ?? TransparencyRecordType::allAllowedExtensions();

                                return 'extensions:'.implode(',', $extensions);
                            })
                            ->downloadable()
                            ->openable()
                            ->extraAttributes(['data-transparency-documents-upload' => 'true'])
                            ->columnSpanFull()
                            ->validationMessages([
                                'max' => 'Cada archivo adjunto no debe superar los 15 MB.',
                                'mimetypes' => 'El formato del archivo no está permitido para la categoría seleccionada.',
                                'mimes' => 'El formato del archivo no está permitido para la categoría seleccionada.',
                                'extensions' => 'El formato del archivo no está permitido para la categoría seleccionada.',
                            ])
                            ->getUploadedFileUsing(static function (SpatieMediaLibraryFileUpload $component, string $file): ?array {
                                $record = $component->getRecord();
                                if (! $record instanceof TransparencyRecord) {
                                    return null;
                                }

                                $media = self::findRecordMediaByUuid($record, $file);

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

                        View::make('filament.transparency-records.document-scanner')
                            ->columnSpanFull(),
                    ]),

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

                                        $media = self::findRecordMediaByUuid($record, $file);

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
                                        && $record->isPublico()
                                        && $record->hasAttachedDocuments()) {
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

    private static function isActiveDirectiva(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && (bool) $user->is_active
            && $user->isLeaderOrAdmin();
    }

    private static function findRecordMediaByUuid(TransparencyRecord $record, string $uuid): ?Media
    {
        $media = $record->getRelationValue('media')?->firstWhere('uuid', $uuid)
            ?? $record->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION)->firstWhere('uuid', $uuid);

        return $media instanceof Media ? $media : null;
    }

    private static function resolveDocumentItem(mixed $item, ?TransparencyRecord $record): TemporaryUploadedFile|Media|null
    {
        if ($item instanceof TemporaryUploadedFile) {
            return $item;
        }

        if (is_string($item) && $item !== '' && $record instanceof TransparencyRecord) {
            return self::findRecordMediaByUuid($record, $item);
        }

        return null;
    }

    /**
     * @param  array<mixed>  $documentsState
     */
    private static function resolveFirstDocument(array $documentsState, ?TransparencyRecord $record): TemporaryUploadedFile|Media|null
    {
        foreach ($documentsState as $item) {
            $document = self::resolveDocumentItem($item, $record);

            if ($document !== null) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $documentsState
     * @return list<array{label: string, document: TemporaryUploadedFile|Media}>
     */
    private static function resolveAnalyzableDocuments(array $documentsState, ?TransparencyRecord $record): array
    {
        $items = [];

        foreach ($documentsState as $item) {
            $document = self::resolveDocumentItem($item, $record);

            if ($document === null) {
                continue;
            }

            $label = $document instanceof TemporaryUploadedFile
                ? ($document->getClientOriginalName() ?: 'Archivo temporal')
                : (string) ($document->file_name ?: $document->name);

            $items[] = [
                'label' => $label,
                'document' => $document,
            ];
        }

        return $items;
    }

    /**
     * @param  array<mixed>  $documentsState
     * @return array<string, string>
     */
    private static function resolveDocumentOptions(array $documentsState, ?TransparencyRecord $record): array
    {
        $options = [];

        foreach (self::resolveAnalyzableDocuments($documentsState, $record) as $index => $entry) {
            $position = $index + 1;
            $options['doc:'.$index] = "{$position}. {$entry['label']}";
        }

        return $options;
    }

    /**
     * @param  array<mixed>  $documentsState
     */
    private static function resolveSelectedDocument(
        array $documentsState,
        ?TransparencyRecord $record,
        ?string $selectedKey,
    ): TemporaryUploadedFile|Media|null {
        $items = self::resolveAnalyzableDocuments($documentsState, $record);

        if ($items === []) {
            return null;
        }

        if (filled($selectedKey) && str_starts_with($selectedKey, 'doc:')) {
            $index = (int) substr($selectedKey, 4);

            if (isset($items[$index])) {
                return $items[$index]['document'];
            }
        }

        return $items[0]['document'];
    }

    /**
     * @return array{heading: ?string, description: ?string, submitLabel: ?string}
     */
    private static function resolveAutofillModalCopy(Get $get, ?TransparencyRecord $record): array
    {
        $hasMultiple = count(self::resolveAnalyzableDocuments($get->array('documents'), $record)) >= 2;
        $hasFilled = self::hasFilledMetadataFields($get);

        return match (true) {
            $hasMultiple && $hasFilled => [
                'heading' => 'Seleccionar documento y confirmar sobrescritura',
                'description' => 'Elige qué archivo analizar con IA. Advertencia: los metadatos capturados actualmente serán sobrescritos con los datos extraídos del documento seleccionado.',
                'submitLabel' => 'Sí, analizar y sobrescribir',
            ],
            $hasMultiple => [
                'heading' => 'Seleccionar documento para analizar con IA',
                'description' => 'Hay varios archivos adjuntos. Elige cuál documento analizar para proponer los metadatos sin consumir tokens adicionales en los anexos.',
                'submitLabel' => 'Analizar documento',
            ],
            $hasFilled => [
                'heading' => '¿Sobrescribir metadatos actuales con IA?',
                'description' => 'Ya existen metadatos capturados en este registro. Si continúas, la IA los sobrescribirá con la información extraída del documento adjunto.',
                'submitLabel' => 'Sí, autocompletar',
            ],
            default => [
                'heading' => null,
                'description' => null,
                'submitLabel' => null,
            ],
        };
    }

    private static function hasFilledMetadataFields(Get $get): bool
    {
        return self::resolveType($get('type')) !== null
            || filled($get('name'))
            || filled($get('summary'))
            || filled($get('period'))
            || filled($get('document_date'))
            || filled($get('reference_number'));
    }

    private static function hasAnyEmptyMetadataField(Get $get): bool
    {
        $type = self::resolveType($get('type'));

        if ($type === null || blank($get('name')) || blank($get('summary'))) {
            return true;
        }

        if ($type->usesPeriod()) {
            return blank($get('fiscal_year')) || blank($get('period'));
        }

        if ($type->usesDocumentDate() && blank($get('document_date'))) {
            return true;
        }

        return $type->usesFolio() && blank($get('reference_number'));
    }

    private static function runAiAnalysis(
        TemporaryUploadedFile|Media|null $document,
        Get $get,
        Set $set,
        bool $overwrite,
    ): void {
        $aiService = app(TransparencyDocumentAIService::class);

        if ($document instanceof TemporaryUploadedFile) {
            $result = $aiService->analyzeTemporaryUpload($document);
        } elseif ($document instanceof Media) {
            $result = $aiService->analyzeMedia($document);
        } else {
            Notification::make()
                ->warning()
                ->title('No se pudo leer el archivo adjunto')
                ->body('Adjunta un documento válido para analizarlo con IA o captura los datos manualmente.')
                ->send();

            return;
        }

        if (! $result->successful) {
            Notification::make()
                ->warning()
                ->title('No se pudo autocompletar con IA')
                ->body($result->errorMessage ?? 'Puedes continuar capturando los metadatos manualmente.')
                ->send();

            return;
        }

        self::applyAiResultToForm($result, $get, $set, overwrite: $overwrite);

        Notification::make()
            ->success()
            ->title('Metadatos propuestos con IA')
            ->body('Revisa la categoría y los campos completados antes de guardar el registro.')
            ->send();
    }

    private static function applyAiResultToForm(
        TransparencyAiMetadataResult $result,
        Get $get,
        Set $set,
        bool $overwrite = true,
    ): void {
        $existingType = self::resolveType($get('type'));
        $effectiveType = $overwrite ? $result->type : ($existingType ?? $result->type);

        if ($overwrite) {
            $set('type', $effectiveType?->value);
            $set('name', $result->name ?? '');
            $set('summary', $result->summary ?? '');
        } else {
            if ($existingType === null && $result->type !== null) {
                $set('type', $result->type->value);
            }

            if (blank($get('name')) && filled($result->name)) {
                $set('name', $result->name);
            }

            if (blank($get('summary')) && filled($result->summary)) {
                $set('summary', $result->summary);
            }
        }

        if ($effectiveType?->usesPeriod()) {
            if ($overwrite || $existingType === null || blank($get('fiscal_year'))) {
                $set('fiscal_year', $result->fiscalYear ?? now()->year);
            }

            if ($overwrite || (blank($get('period')) && filled($result->period))) {
                $set('period', $result->period);
            }

            if ($overwrite) {
                $set('document_date', null);
                $set('reference_number', null);
            }
        } elseif ($overwrite) {
            $set('period', null);
            $set('document_date', $effectiveType?->usesDocumentDate() ? $result->documentDate : null);
            $set('reference_number', $effectiveType?->usesFolio() ? $result->referenceNumber : null);
        } else {
            if (($effectiveType?->usesDocumentDate() ?? false) && blank($get('document_date')) && filled($result->documentDate)) {
                $set('document_date', $result->documentDate);
            }

            if (($effectiveType?->usesFolio() ?? false) && blank($get('reference_number')) && filled($result->referenceNumber)) {
                $set('reference_number', $result->referenceNumber);
            }
        }

        if ($get->enum('status', TransparencyRecordStatus::class) === null) {
            $set('status', TransparencyRecordStatus::Interno->value);
        }
    }
}
