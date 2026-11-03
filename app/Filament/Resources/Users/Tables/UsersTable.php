<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(fn (User $record): ?string => filament()->getUserAvatarUrl($record)),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('surnames')
                    ->label('Apellidos')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->searchable(),
                TextColumn::make('document_status')
                    ->label('Documentación')
                    ->badge()
                    ->state(fn (User $record): string => $record->getOverallDocumentStatus()->getLabel())
                    ->color(fn (User $record): string => $record->getOverallDocumentStatus()->getColor())
                    ->icon(fn (User $record): ?string => $record->getOverallDocumentStatus()->getIcon()),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                TextColumn::make('curp')
                    ->label('CURP')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->date()
                    ->sortable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('category')
                    ->label('Categoría')
                    ->searchable(),
                TextColumn::make('nss')
                    ->label('Número de Seguridad Social')
                    ->searchable(),
                TextColumn::make('salary')
                    ->label('Salario')
                    ->money('MXN', true)
                    ->numeric()
                    ->sortable(),
                TextColumn::make('hiring_date')
                    ->label('Fecha de Contratación')
                    ->date()
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label('Fecha de Eliminación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('reviewDocuments')
                    ->label('Revisar')
                    ->icon(Heroicon::DocumentMagnifyingGlass)
                    ->color('info')
                    ->url(fn (User $record): string => UserResource::getUrl('review-documents', ['record' => $record])),

                EditAction::make(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('surnames')->orderBy('name'))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
