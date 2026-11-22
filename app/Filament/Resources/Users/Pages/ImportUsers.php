<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ImportUsers extends Page
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Carga Masiva de Padrón y Documentos';

    protected static ?string $breadcrumb = 'Importar Padrón';

    protected string $view = 'filament.resources.users.pages.import-users';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isLeaderOrAdmin() ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al Padrón')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->url(UserResource::getUrl('index')),
        ];
    }
}
