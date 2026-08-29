<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

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
                    ->searchable(),
                TextColumn::make('surnames')
                    ->label('Apellidos')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable(),
                TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->searchable(),
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
                    ->label('Eliminado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
