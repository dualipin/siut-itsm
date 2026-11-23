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
use Filament\Schemas\Components\Callout;
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
                            ->action(static function (Get $get, Set $set, ?TransparencyRecord $record): void {
                                if (! self::isActiveDirectiva()) {
                                    return;
                                }

                                $firstDocument = self::resolveFirstDocument($get->array('documents'), $record);
                                $aiService = app(TransparencyDocumentAIService::class);

                                if ($firstDocument instanceof TemporaryUploadedFile) {
                                    $result = $aiService->analyzeTemporaryUpload($firstDocument);
                                } elseif ($firstDocument instanceof Media) {
                                    $result = $aiService->analyzeMedia($firstDocument);
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

                                self::applyAiResultToForm($result, $get, $set, $record);

                                Notification::make()
                                    ->success()
                                    ->title('Metadatos propuestos con IA')
                                    ->body('Revisa la categoría y los campos completados antes de guardar el registro.')
                                    ->send();
                            }),
                    ])
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('documents')
                            ->label('Archivos')
                            ->collection(TransparencyRecord::DOCUMENTS_COLLECTION)
                            ->multiple()
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

                                $media = $record->getRelationValue('media')?->firstWhere('uuid', $file)
                                    ?? $record->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION)->firstWhere('uuid', $file);

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

                                        $media = $record->getRelationValue('media')?->firstWhere('uuid', $file)
                                            ?? $record->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION)->firstWhere('uuid', $file);

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
                        Hidden::make('ai_pii_detected')
                            ->default(false)
                            ->dehydrated(false),

                        Hidden::make('ai_pii_reason')
                            ->default(null)
                            ->dehydrated(false),

                        Callout::make('Posibles datos personales sensibles detectados')
                            ->warning()
                            ->icon(Heroicon::ExclamationTriangle)
                            ->description(static function (Get $get): string {
                                $reason = trim((string) $get->string('ai_pii_reason'));
                                $base = 'La IA detectó información personal sensible en el documento. El registro se mantiene en Consulta Interna para proteger la privacidad de los agremiados.';

                                return $reason !== '' ? "{$base} Motivo sugerido: {$reason}" : $base;
                            })
                            ->visible(static fn (Get $get): bool => (bool) $get('ai_pii_detected'))
                            ->columnSpanFull(),

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

    /**
     * @param  array<mixed>  $documentsState
     */
    private static function resolveFirstDocument(array $documentsState, ?TransparencyRecord $record): TemporaryUploadedFile|Media|null
    {
        foreach ($documentsState as $item) {
            if ($item instanceof TemporaryUploadedFile) {
                return $item;
            }

            if (is_string($item) && $item !== '' && $record instanceof TransparencyRecord) {
                $media = $record->getRelationValue('media')?->firstWhere('uuid', $item)
                    ?? $record->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION)->firstWhere('uuid', $item);

                if ($media instanceof Media) {
                    return $media;
                }
            }
        }

        return null;
    }

    public static function applyAiResultToForm(
        TransparencyAiMetadataResult $result,
        Get $get,
        Set $set,
        ?TransparencyRecord $record = null,
        bool $onlyEmpty = false,
    ): void {
        if (! $onlyEmpty || blank($get('type'))) {
            $set('type', $result->type?->value);
        }

        $effectiveType = $onlyEmpty
            ? (self::resolveType($get('type')) ?? $result->type)
            : $result->type;

        if (! $onlyEmpty || blank($get('name'))) {
            $set('name', $result->name ?? '');
        }

        if (! $onlyEmpty || blank($get('summary'))) {
            $set('summary', $result->summary ?? '');
        }

        $appliedReferenceNumber = null;

        if ($effectiveType?->usesPeriod()) {
            if (! $onlyEmpty || blank($get('fiscal_year'))) {
                $set('fiscal_year', $result->fiscalYear ?? now()->year);
            }

            if (! $onlyEmpty || blank($get('period'))) {
                $set('period', $result->period);
            }

            $set('document_date', null);
            $set('reference_number', null);
        } else {
            $set('period', null);

            if (! ($effectiveType?->usesDocumentDate() ?? false)) {
                $set('document_date', null);
            } elseif (! $onlyEmpty || blank($get('document_date'))) {
                $set('document_date', $result->documentDate);
            }

            if (! ($effectiveType?->usesFolio() ?? false)) {
                $set('reference_number', null);
            } elseif (! $onlyEmpty || blank($get('reference_number'))) {
                $set('reference_number', $result->referenceNumber);
                $appliedReferenceNumber = $result->referenceNumber;
            }
        }

        self::notifyIfDuplicateReferenceNumber($appliedReferenceNumber, $record);

        if ($result->containsPersonalData) {
            $suggestion = filled($result->internalReasonSuggestion)
                ? $result->internalReasonSuggestion
                : 'Contiene datos personales sensibles; se mantiene en Consulta Interna.';

            $set('status', TransparencyRecordStatus::Interno->value);
            $set('ai_pii_detected', true);
            $set('ai_pii_reason', $suggestion);

            if (! $onlyEmpty || blank($get('observations'))) {
                $set('observations', $suggestion);
            }
        } else {
            $set('ai_pii_detected', false);
            $set('ai_pii_reason', null);

            if ($get->enum('status', TransparencyRecordStatus::class) === null) {
                $set('status', TransparencyRecordStatus::Interno->value);
            }
        }
    }

    private static function notifyIfDuplicateReferenceNumber(?string $referenceNumber, ?TransparencyRecord $record): void
    {
        $folio = trim((string) $referenceNumber);

        if ($folio === '') {
            return;
        }

        $duplicate = TransparencyRecord::query()
            ->where('reference_number', $folio)
            ->when(
                $record instanceof TransparencyRecord && $record->exists,
                static fn ($query) => $query->whereKeyNot($record->getKey()),
            )
            ->first(['id', 'name']);

        if (! $duplicate) {
            return;
        }

        Notification::make()
            ->warning()
            ->title('Posible folio duplicado')
            ->body("El folio \"{$folio}\" ya está registrado en otro documento ({$duplicate->name}). Revisa antes de guardar.")
            ->send();
    }
}
