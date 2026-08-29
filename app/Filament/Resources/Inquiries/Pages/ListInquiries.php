<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Filament\Resources\Inquiries\InquiryResource;
use Filament\Resources\Pages\ListRecords;

class ListInquiries extends ListRecords
{
    protected static string $resource = InquiryResource::class;

    protected static ?string $title = 'Bandeja de Dudas y Consultas';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
