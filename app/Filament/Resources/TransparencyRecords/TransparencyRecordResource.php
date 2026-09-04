<?php

namespace App\Filament\Resources\TransparencyRecords;

use App\Filament\Resources\TransparencyRecords\Pages\CreateTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\Pages\EditTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\Pages\ListTransparencyRecords;
use App\Filament\Resources\TransparencyRecords\Pages\ViewTransparencyRecord;
use App\Filament\Resources\TransparencyRecords\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\TransparencyRecords\Schemas\TransparencyRecordForm;
use App\Filament\Resources\TransparencyRecords\Schemas\TransparencyRecordInfolist;
use App\Filament\Resources\TransparencyRecords\Tables\TransparencyRecordsTable;
use App\Models\TransparencyRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class TransparencyRecordResource extends Resource
{
    protected static ?string $model = TransparencyRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentText;

    protected static UnitEnum|string|null $navigationGroup = 'Transparencia y Finanzas';

    protected static ?string $breadcrumb = 'Transparencia';

    protected static ?string $navigationLabel = 'Transparencia';

    protected static ?string $pluralModelLabel = 'registros de transparencia';

    protected static ?string $modelLabel = 'Registro de Transparencia';

    public static function form(Schema $schema): Schema
    {
        return TransparencyRecordForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TransparencyRecordInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TransparencyRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransparencyRecords::route('/'),
            'create' => CreateTransparencyRecord::route('/create'),
            'view' => ViewTransparencyRecord::route('/{record}'),
            'edit' => EditTransparencyRecord::route('/{record}/edit'),
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
