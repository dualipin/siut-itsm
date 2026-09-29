<?php

namespace App\Filament\Resources\RequestTypes;

use App\Filament\Resources\RequestTypes\Pages\CreateRequestType;
use App\Filament\Resources\RequestTypes\Pages\EditRequestType;
use App\Filament\Resources\RequestTypes\Pages\ListRequestTypes;
use App\Filament\Resources\RequestTypes\Schemas\RequestTypeForm;
use App\Filament\Resources\RequestTypes\Tables\RequestTypesTable;
use App\Models\RequestType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RequestTypeResource extends Resource
{
    protected static ?string $model = RequestType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::SquaresPlus;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Tipo de Solicitud';

    protected static ?string $pluralModelLabel = 'tipos de solicitud';

    protected static ?string $navigationLabel = 'Tipos de Solicitud';

    protected static ?string $breadcrumb = 'Tipos de Solicitud';

    protected static \UnitEnum|string|null $navigationGroup = 'Administración';

    public static function canAccess(): bool
    {
        return auth()->user()?->isLeaderOrAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return RequestTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RequestTypesTable::configure($table);
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
            'index' => ListRequestTypes::route('/'),
            'create' => CreateRequestType::route('/create'),
            'edit' => EditRequestType::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
