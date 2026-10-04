<?php

namespace App\Filament\Resources\PetitionRequests;

use App\Filament\Resources\PetitionRequests\Pages\ListPetitionRequests;
use App\Filament\Resources\PetitionRequests\Pages\ViewPetitionRequest;
use App\Filament\Resources\PetitionRequests\Schemas\PetitionRequestInfolist;
use App\Filament\Resources\PetitionRequests\Tables\PetitionRequestsTable;
use App\Models\PetitionRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PetitionRequestResource extends Resource
{
    protected static ?string $model = PetitionRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $recordTitleAttribute = 'proposal';

    protected static ?string $modelLabel = 'Petición de Pliego';

    protected static ?string $pluralModelLabel = 'peticiones de pliego';

    protected static ?string $navigationLabel = null;

    protected static ?string $breadcrumb = 'Peticiones de Pliego';

    protected static \UnitEnum|string|null $navigationGroup = 'Administración';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return PetitionRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PetitionRequestsTable::configure($table);
    }

    /**
     * @return Builder<PetitionRequest>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['annualPetition', 'media']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPetitionRequests::route('/'),
            'view' => ViewPetitionRequest::route('/{record}'),
        ];
    }
}
