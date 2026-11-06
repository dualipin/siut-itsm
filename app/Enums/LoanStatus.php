<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanStatus: string implements HasColor, HasLabel
{
    case Borrador = 'borrador';
    case Enviada = 'enviada';
    case EnRevision = 'en_revision';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';
    case ListaEspera = 'lista_espera';
    case Desembolsada = 'desembolsada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviada => 'Enviada',
            self::EnRevision => 'En revisión',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
            self::ListaEspera => 'Lista de espera',
            self::Desembolsada => 'Desembolsada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Enviada => 'info',
            self::EnRevision => 'warning',
            self::Aprobada => 'success',
            self::Rechazada => 'danger',
            self::ListaEspera => 'warning',
            self::Desembolsada => 'success',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::Borrador,
            self::Enviada,
            self::EnRevision,
            self::Aprobada,
            self::ListaEspera,
            self::Desembolsada,
        ], true);
    }
}
