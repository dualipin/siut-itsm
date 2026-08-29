<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransparencyRecordStatus: string implements HasColor, HasLabel
{
    case Borrador = 'borrador';
    case Revision = 'revision';
    case Publicado = 'publicado';
    case Archivado = 'archivado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Revision => 'En Revisión',
            self::Publicado => 'Publicado',
            self::Archivado => 'Archivado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Revision => 'warning',
            self::Publicado => 'success',
            self::Archivado => 'danger',
        };
    }
}
