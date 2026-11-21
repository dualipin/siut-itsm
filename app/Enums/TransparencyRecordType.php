<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransparencyRecordType: string implements HasColor, HasLabel
{
    case Financiero = 'financiero';
    case Oficios = 'oficios';
    case Actas = 'actas';
    case Legal = 'legal';
    case Otros = 'otros';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Financiero => 'Financiero',
            self::Oficios => 'Oficios y Gestiones',
            self::Actas => 'Actas y Minutas',
            self::Legal => 'Normativo y Legal',
            self::Otros => 'Otros',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Financiero => 'success',
            self::Oficios => 'info',
            self::Actas => 'primary',
            self::Legal => 'warning',
            self::Otros => 'gray',
        };
    }

    public function getSlug(): string
    {
        return $this->value;
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Financiero => 'Balances contables, presupuestos de operación, estados financieros y reportes periódicos del sindicato.',
            self::Oficios => 'Oficios enviados y recibidos, gestiones institucionales, solicitudes y correspondencia sindical.',
            self::Actas => 'Actas oficiales, minutas y acuerdos emanados de asambleas y sesiones del comité.',
            self::Legal => 'Estatutos, reglamentos, convenios colectivos, contratos y resoluciones normativas.',
            self::Otros => 'Documentos informativos, memorias históricas y archivo general de transparencia.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Financiero => 'bi-cash-coin',
            self::Oficios => 'bi-briefcase',
            self::Actas => 'bi-file-earmark-check',
            self::Legal => 'bi-file-earmark-ruled',
            self::Otros => 'bi-folder2-open',
        };
    }

    public function usesPeriod(): bool
    {
        return $this === self::Financiero;
    }

    public function usesDocumentDate(): bool
    {
        return $this !== self::Financiero;
    }

    public function usesFolio(): bool
    {
        return in_array($this, [self::Oficios, self::Actas, self::Legal], true);
    }

    /**
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        $base = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        if ($this === self::Financiero) {
            return [...$base, 'xlsx', 'xls', 'csv'];
        }

        return $base;
    }

    /**
     * @return list<string>
     */
    public function allowedMimeTypes(): array
    {
        $base = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if ($this === self::Financiero) {
            return [
                ...$base,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv',
                'application/csv',
            ];
        }

        return $base;
    }

    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom(strtolower(trim($slug)));
    }

    /**
     * Closed list of financial period options.
     *
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        $periods = [
            'Anual',
            '1er Trimestre',
            '2do Trimestre',
            '3er Trimestre',
            '4to Trimestre',
            '1er Semestre',
            '2do Semestre',
            'Enero',
            'Febrero',
            'Marzo',
            'Abril',
            'Mayo',
            'Junio',
            'Julio',
            'Agosto',
            'Septiembre',
            'Octubre',
            'Noviembre',
            'Diciembre',
        ];

        return array_combine($periods, $periods);
    }
}
