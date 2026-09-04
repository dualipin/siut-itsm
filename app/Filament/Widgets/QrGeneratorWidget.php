<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QrGeneratorWidget extends Widget
{
    protected string $view = 'filament.widgets.qr-generator-widget';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;
}
