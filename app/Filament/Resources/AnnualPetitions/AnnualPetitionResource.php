<?php

namespace App\Filament\Resources\AnnualPetitions;

use App\Filament\Resources\AnnualPetitions\Pages\CreateAnnualPetition;
use App\Filament\Resources\AnnualPetitions\Pages\EditAnnualPetition;
use App\Filament\Resources\AnnualPetitions\Pages\ListAnnualPetitions;
use App\Filament\Resources\AnnualPetitions\Schemas\AnnualPetitionForm;
use App\Filament\Resources\AnnualPetitions\Tables\AnnualPetitionsTable;
use App\Models\AnnualPetition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnnualPetitionResource extends Resource
{
    protected static ?string $model = AnnualPetition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $recordTitleAttribute = 'year';

    protected static ?string $modelLabel = 'Pliego Anual';

    protected static ?string $pluralModelLabel = 'pliegos anuales';

    protected static ?string $navigationLabel = 'Pliegos Anuales';

    protected static ?string $breadcrumb = 'Pliegos Anuales';

    protected static \UnitEnum|string|null $navigationGroup = 'Administración';

    public static function form(Schema $schema): Schema
    {
        return AnnualPetitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnnualPetitionsTable::configure($table);
    }

    /**
     * @return Builder<AnnualPetition>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('petitionRequests');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PetitionRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnualPetitions::route('/'),
            'create' => CreateAnnualPetition::route('/create'),
            'edit' => EditAnnualPetition::route('/{record}/edit'),
        ];
    }
}
