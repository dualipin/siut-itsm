<?php

namespace App\Actions;

use App\Models\User;
use App\Support\DocumentThemePalette;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Barryvdh\DomPDF\PDF;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ExportUsersPdf
{
    public const MAX_COLUMNS = 5;

    /** @var list<string> */
    public const DEFAULT_COLUMNS = ['full_name', 'curp', 'category', 'phone', 'email'];

    /** @var array<string, string> */
    public const AVAILABLE_COLUMNS = [
        'full_name' => 'Nombre completo',
        'email' => 'Correo electrónico',
        'curp' => 'CURP',
        'nss' => 'NSS',
        'phone' => 'Teléfono',
        'category' => 'Categoría',
        'address' => 'Dirección',
        'role' => 'Rol',
        'is_active' => 'Estatus de cuenta',
        'birth_date' => 'Fecha de nacimiento',
        'hiring_date' => 'Fecha de ingreso',
        'document_status' => 'Expediente',
    ];

    public function __construct(
        protected readonly DocumentThemePalette $documentThemePalette,
    ) {}

    /**
     * @param  Builder<User>  $query
     * @param  list<string>  $columns
     */
    public function execute(Builder $query, array $columns): PDF
    {
        $selected = array_values(array_unique($columns));
        $invalid = array_diff($selected, array_keys(self::AVAILABLE_COLUMNS));

        if ($selected === [] || count($selected) > self::MAX_COLUMNS || $invalid !== []) {
            throw new InvalidArgumentException('Selecciona entre 1 y 5 columnas válidas para exportar.');
        }

        $needsMedia = in_array('document_status', $selected, true);
        $baseQuery = (clone $query)
            ->when($needsMedia, fn (Builder $q): Builder => $q->with('media'))
            ->orderBy('name')
            ->orderBy('surnames');

        $rows = new Collection;

        $baseQuery->chunk(200, function (Collection $chunk) use ($rows, $selected): void {
            foreach ($chunk as $user) {
                /** @var User $user */
                $row = [];
                foreach ($selected as $key) {
                    $row[$key] = self::resolveValue($user, $key);
                }
                $rows->push($row);
            }
        });

        $headers = array_map(fn (string $key): string => self::AVAILABLE_COLUMNS[$key], $selected);

        return DomPdf::loadView('pdf.users-directory', [
            'headers' => $headers,
            'columnKeys' => $selected,
            'rows' => $rows,
            'documentColors' => $this->documentThemePalette->resolve(),
            'logoSrc' => public_path('assets/images/logo.webp'),
        ])->setPaper('letter', count($selected) >= 4 ? 'landscape' : 'portrait');
    }

    public static function resolveValue(User $user, string $key): string
    {
        $value = match ($key) {
            'full_name' => $user->full_name,
            'email' => $user->email,
            'curp' => $user->curp,
            'nss' => $user->nss,
            'phone' => $user->phone,
            'category' => $user->category,
            'address' => $user->address,
            'role' => $user->role?->getLabel() ?? $user->role?->value,
            'is_active' => $user->is_active ? 'Activo' : 'Inactivo',
            'birth_date' => $user->birth_date?->format('d/m/Y'),
            'hiring_date' => $user->hiring_date?->format('d/m/Y'),
            'document_status' => $user->getOverallDocumentStatus()->getLabel(),
            default => null,
        };

        return filled($value) ? (string) $value : '—';
    }
}
