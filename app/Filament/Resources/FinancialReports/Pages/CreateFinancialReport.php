<?php

namespace App\Filament\Resources\FinancialReports\Pages;

use App\Filament\Resources\FinancialReports\FinancialReportResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinancialReport extends CreateRecord
{
    protected static ?string $title = 'Nuevo Reporte Financiero';

    protected static string $resource = FinancialReportResource::class;
}
