<?php

namespace App\Filament\Resources\TransparencyRecords\RelationManagers;

use App\Models\TransparencyDocument;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentos';

    protected static ?string $modelLabel = 'Documento';

    protected static ?string $pluralModelLabel = 'Documentos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre del Documento')
                    ->placeholder('Ej. Contrato Colectivo 2026, Reporte de Horas Diciembre 2025')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('published_at')
                    ->label('Fecha de Publicación / Vigencia')
                    ->helperText('Fecha en que se publicó o a la que corresponde el documento'),
                Toggle::make('is_public')
                    ->label('¿Es Público?')
                    ->helperText('Determina si este documento estará visible al público')
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
                            ->label('Abrir Archivo')
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
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')
                    ->label('Nombre del Documento'),
                TextEntry::make('published_at')
                    ->label('Fecha de Publicación')
                    ->date()
                    ->placeholder('-'),
                IconEntry::make('is_public')
                    ->label('Público')
                    ->boolean(),
                TextEntry::make('file')
                    ->label('Archivo')
                    ->state(fn ($record): string => $record->getFirstMedia('file')?->file_name ?? 'Ninguno')
                    ->url(fn ($record): ?string => $record->hasMedia('file') ? route('portal.transparency.documents.show', $record) : null)
                    ->openUrlInNewTab()
                    ->icon(Heroicon::ArrowTopRightOnSquare)
                    ->color(fn ($record): ?string => $record->hasMedia('file') ? 'primary' : 'gray'),
                TextEntry::make('uploader.name')
                    ->label('Subido por')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->heading('Documentos del Registro')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Fecha Publicación')
                    ->date()
                    ->sortable(),
                ToggleColumn::make('is_public')
                    ->label('Público')
                    ->sortable(),
                TextColumn::make('media.file_name')
                    ->label('Archivo')
                    ->default('Sin archivo')
                    ->limit(30),
                TextColumn::make('created_at')
                    ->label('Subido el')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo Documento'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Abrir')
                    ->icon(Heroicon::ArrowTopRightOnSquare)
                    ->url(fn (TransparencyDocument $record): string => route('portal.transparency.documents.show', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (TransparencyDocument $record): bool => $record->hasMedia('file')),
                Action::make('download')
                    ->label('Descargar')
                    ->icon(Heroicon::ArrowDownTray)
                    ->url(fn (TransparencyDocument $record): string => route('portal.transparency.documents.show', ['document' => $record, 'download' => 1]))
                    ->openUrlInNewTab()
                    ->visible(fn (TransparencyDocument $record): bool => $record->hasMedia('file')),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
