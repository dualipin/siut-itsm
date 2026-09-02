<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DocumentStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pendiente';
    case Valid = 'valido';
    case Invalid = 'invalido';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pendiente de Revisión',
            self::Valid => 'Válido',
            self::Invalid => 'Inválido / Observado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Valid => 'success',
            self::Invalid => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-clock',
            self::Valid => 'heroicon-m-check-circle',
            self::Invalid => 'heroicon-m-x-circle',
        };
    }
}
