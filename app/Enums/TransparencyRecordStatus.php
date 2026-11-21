<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransparencyRecordStatus: string implements HasColor, HasLabel
{
    case Interno = 'interno';
    case Publico = 'publico';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Interno => 'Consulta Interna',
            self::Publico => 'Público',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Interno => 'warning',
            self::Publico => 'success',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Interno => 'El registro aparece listado en el portal, pero sus archivos quedan bloqueados al público y solo la Directiva puede consultarlos.',
            self::Publico => 'El registro aparece listado en el portal y cualquier visitante puede descargar sus archivos adjuntos.',
        };
    }
}
