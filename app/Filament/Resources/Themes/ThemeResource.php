<?php

namespace App\Filament\Resources\Themes;

use App\Filament\Resources\Themes\Pages\EditTheme;
use App\Filament\Resources\Themes\Pages\ListThemes;
use App\Filament\Resources\Themes\Schemas\ThemeForm;
use App\Filament\Resources\Themes\Tables\ThemesTable;
use App\Models\Theme;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ThemeResource extends Resource
{
    protected static ?string $model = Theme::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Swatch;

    protected static UnitEnum|string|null $navigationGroup = 'Administración';

    protected static ?string $breadcrumb = 'Apariencia';

    protected static ?string $navigationLabel = 'Apariencia';

    protected static ?string $pluralModelLabel = 'configuraciones de apariencia';

    protected static ?string $modelLabel = 'Configuración de Apariencia';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return ThemeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ThemesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThemes::route('/'),
            'edit' => EditTheme::route('/{record}/edit'),
        ];
    }
}
