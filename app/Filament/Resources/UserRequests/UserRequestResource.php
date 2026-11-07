<?php

namespace App\Filament\Resources\UserRequests;

use App\Filament\Resources\UserRequests\Pages\CreateUserRequest;
use App\Filament\Resources\UserRequests\Pages\EditUserRequest;
use App\Filament\Resources\UserRequests\Pages\ListUserRequests;
use App\Filament\Resources\UserRequests\Pages\ViewUserRequest;
use App\Filament\Resources\UserRequests\Schemas\UserRequestForm;
use App\Filament\Resources\UserRequests\Schemas\UserRequestInfolist;
use App\Filament\Resources\UserRequests\Tables\UserRequestsTable;
use App\Models\User;
use App\Models\UserRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserRequestResource extends Resource
{
    protected static ?string $model = UserRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'folio';

    protected static ?string $modelLabel = 'Solicitud';

    protected static ?string $pluralModelLabel = 'solicitudes';

    protected static ?string $navigationLabel = 'Solicitudes';

    protected static ?string $breadcrumb = 'Solicitudes';

    protected static \UnitEnum|string|null $navigationGroup = 'Administración';

    public static function form(Schema $schema): Schema
    {
        return UserRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserRequests::route('/'),
            'create' => CreateUserRequest::route('/create'),
            'view' => ViewUserRequest::route('/{record}'),
            'edit' => EditUserRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        /** @var User|null $user */
        $user = auth()->user();

        if ($user && $user->isLeaderOrAdmin()) {
            $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
        } elseif ($user && $user->isAgremiado()) {
            $query->where('user_id', $user->id);
        }

        return $query;
    }
}
