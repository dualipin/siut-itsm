<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ConversationStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Closed = 'closed';
    case Archived = 'archived';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => 'Activa',
            self::Closed => 'Cerrada',
            self::Archived => 'Archivada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::Closed => 'danger',
            self::Archived => 'gray',
        };
    }
}
