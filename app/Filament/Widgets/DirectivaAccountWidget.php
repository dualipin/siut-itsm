<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget;

class DirectivaAccountWidget extends AccountWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->isLeaderOrAdmin() ?? false;
    }
}
