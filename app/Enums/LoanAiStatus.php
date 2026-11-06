<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoanAiStatus: string implements HasColor, HasLabel
{
    case Valido = 'valido';
    case Dudoso = 'dudoso';
    case Invalido = 'invalido';
    case FalloTecnico = 'fallo_tecnico';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Valido => 'Válido',
            self::Dudoso => 'Dudoso',
            self::Invalido => 'Inválido',
            self::FalloTecnico => 'Fallo técnico',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Valido => 'success',
            self::Dudoso => 'warning',
            self::Invalido => 'danger',
            self::FalloTecnico => 'gray',
        };
    }

    public function requiresManualReview(): bool
    {
        return in_array($this, [self::Dudoso, self::Invalido, self::FalloTecnico], true);
    }
}
