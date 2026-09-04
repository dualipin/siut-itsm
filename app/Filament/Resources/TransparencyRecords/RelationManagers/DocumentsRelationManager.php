<?php

namespace App\Filament\Resources\TransparencyRecords\RelationManagers;

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
                    ->columnSpanFull(),
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
                    ->url(fn ($record): ?string => $record->getFirstMediaUrl('file') ?: null)
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-down-tray')
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
                Action::make('download')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn ($record) => $record->getFirstMediaUrl('file'))
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->hasMedia('file')),
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
