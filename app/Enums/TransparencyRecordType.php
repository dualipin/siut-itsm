<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransparencyRecordType: string implements HasColor, HasLabel
{
    case FINANCIERO = 'FINANCIERO';
    case ADMINISTRATIVO = 'ADMINISTRATIVO';
    case LEGAL = 'LEGAL';
    case SINDICAL = 'SINDICAL';
    case GESTORIA = 'GESTORIA';
    case GREMIALES = 'GREMIALES';
    case TRAMITES = 'TRAMITES';
    case MINUTAS = 'MINUTAS';
    case OTRO = 'OTRO';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FINANCIERO => 'Financiero',
            self::ADMINISTRATIVO => 'Administrativo',
            self::LEGAL => 'Legal',
            self::SINDICAL => 'Sindical',
            self::GESTORIA => 'Gestoría',
            self::GREMIALES => 'Gremiales',
            self::TRAMITES => 'Trámites',
            self::MINUTAS => 'Minutas',
            self::OTRO => 'Otro',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::FINANCIERO => 'success',
            self::ADMINISTRATIVO => 'info',
            self::LEGAL => 'warning',
            self::SINDICAL => 'primary',
            self::GESTORIA => 'secondary',
            self::GREMIALES => 'danger',
            self::TRAMITES => 'warning',
            self::MINUTAS => 'info',
            self::OTRO => 'gray',
        };
    }

    public function getSlug(): string
    {
        return strtolower($this->value);
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::FINANCIERO => 'Consulta los balances contables, presupuestos de operación anuales y dictámenes de egresos del sindicato.',
            self::ADMINISTRATIVO => 'Consulta documentos de gestión interna, acuerdos administrativos y comunicados institucionales.',
            self::LEGAL => 'Consulta los convenios jurídicos, acuerdos legales y resoluciones normativas de la organización.',
            self::SINDICAL => 'Consulta acuerdos estatutarios, resoluciones de la directiva y actas de la vida sindical.',
            self::GESTORIA => 'Consulta trámites, solicitudes e informes de gestoría sindical realizados en favor de los agremiados.',
            self::GREMIALES => 'Consulta información sobre convenios gremiales, acuerdos colectivos y beneficios para los trabajadores.',
            self::TRAMITES => 'Consulta guías, solicitudes y formatos para la realización de trámites y gestiones sindicales.',
            self::MINUTAS => 'Consulta las actas oficiales, minutas y resoluciones emanadas de nuestras asambleas y sesiones de comité.',
            self::OTRO => 'Consulta otros documentos informativos, archivos históricos y de transparencia general de la organización.',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::FINANCIERO => 'bi-cash-coin',
            self::ADMINISTRATIVO => 'bi-building-gear',
            self::LEGAL => 'bi-file-earmark-ruled',
            self::SINDICAL => 'bi-people',
            self::GESTORIA => 'bi-briefcase',
            self::GREMIALES => 'bi-diagram-3',
            self::TRAMITES => 'bi-clipboard-check',
            self::MINUTAS => 'bi-file-earmark-check',
            self::OTRO => 'bi-folder2-open',
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        return self::tryFrom(strtoupper(trim($slug)))
            ?? self::tryFrom(trim($slug));
    }
}
