<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Agremiado = 'agremiado';
    case Lider = 'lider';
    case Admin = 'admin';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Agremiado => 'Agremiado',
            self::Lider => 'Líder',
            self::Admin => 'Administrador',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Agremiado => 'info',
            self::Lider => 'warning',
            self::Admin => 'success',
        };
    }
}
