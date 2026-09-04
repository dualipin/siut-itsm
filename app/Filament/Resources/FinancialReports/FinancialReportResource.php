<?php

namespace App\Filament\Resources\FinancialReports;

use App\Filament\Resources\FinancialReports\Pages\CreateFinancialReport;
use App\Filament\Resources\FinancialReports\Pages\EditFinancialReport;
use App\Filament\Resources\FinancialReports\Pages\ListFinancialReports;
use App\Filament\Resources\FinancialReports\Pages\ViewFinancialReport;
use App\Filament\Resources\FinancialReports\Schemas\FinancialReportForm;
use App\Filament\Resources\FinancialReports\Schemas\FinancialReportInfolist;
use App\Filament\Resources\FinancialReports\Tables\FinancialReportsTable;
use App\Http\Controllers\FinancialReportDocumentController;
use App\Models\FinancialReport;
use BackedEnum;
use Closure;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Route;
use UnitEnum;

class FinancialReportResource extends Resource
{
    protected static ?string $model = FinancialReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::DocumentChartBar;

    protected static UnitEnum|string|null $navigationGroup = 'Transparencia y Finanzas';

    protected static ?string $breadcrumb = 'Reportes Financieros';

    protected static ?string $navigationLabel = 'Reportes Financieros';

    protected static ?string $pluralModelLabel = 'reportes financieros';

    protected static ?string $modelLabel = 'Reporte Financiero';

    public static function form(Schema $schema): Schema
    {
        return FinancialReportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FinancialReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancialReportsTable::configure($table);
    }

    public static function routes(Panel $panel, ?Closure $registerPageRoutes = null): void
    {
        parent::routes($panel, function () use ($registerPageRoutes): void {
            if ($registerPageRoutes) {
                $registerPageRoutes();
            }

            Route::get('/{record}/document', [FinancialReportDocumentController::class, 'show'])
                ->name('document');
        });
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
            'index' => ListFinancialReports::route('/'),
            'create' => CreateFinancialReport::route('/create'),
            'view' => ViewFinancialReport::route('/{record}'),
            'edit' => EditFinancialReport::route('/{record}/edit'),
        ];
    }
}
