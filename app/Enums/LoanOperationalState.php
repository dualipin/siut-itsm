<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanOperationalState: string implements HasColor, HasLabel
{
    case Activo = 'activo';
    case CierreAnio = 'cierre_anio';
    case TemporalmenteFueraDeServicio = 'temporalmente_fuera_de_servicio';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::CierreAnio => 'Cierre de año',
            self::TemporalmenteFueraDeServicio => 'Temporalmente fuera de servicio',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Activo => 'success',
            self::CierreAnio => 'warning',
            self::TemporalmenteFueraDeServicio => 'danger',
        };
    }

    public function isSuspended(): bool
    {
        return $this === self::TemporalmenteFueraDeServicio;
    }

    public function isAcceptingApplications(): bool
    {
        return ! $this->isSuspended();
    }
}
