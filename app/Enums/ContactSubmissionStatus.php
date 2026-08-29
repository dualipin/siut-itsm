<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ContactSubmissionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Replied = 'replied';
    case Archived = 'archived';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Replied => 'Respondido',
            self::Archived => 'Archivado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Replied => 'success',
            self::Archived => 'gray',
        };
    }
}
