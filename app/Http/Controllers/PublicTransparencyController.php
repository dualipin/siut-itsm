<?php

namespace App\Http\Controllers;

use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicTransparencyController extends Controller
{
    /**
     * Closed list of financial periods in display order.
     *
     * @var list<string>
     */
    private const PERIOD_ORDER = [
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

    /**
     * Spanish month names keyed by calendar month number (1-12).
     *
     * @var array<int, string>
     */
    private const SPANISH_MONTHS = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Septiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /**
     * Display a listing of public transparency records for a specific type.
     */
    public function index(Request $request, string $type): View
    {
        $typeEnum = TransparencyRecordType::fromSlug($type);

        if ($typeEnum === null) {
            abort(404, 'Tipo de registro de transparencia no encontrado.');
        }

        // ADR-0001: list both Publico and Interno records; download links render only for Publico.
        $records = TransparencyRecord::query()
            ->where('type', $typeEnum)
            ->with('media')
            ->orderBy('fiscal_year', 'desc')
            ->orderBy('document_date', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        $groupedRecords = $records
            ->groupBy('fiscal_year')
            ->sortKeysDesc()
            ->map(fn (Collection $yearRecords): Collection => $this->groupYearRecords($yearRecords, $typeEnum));

        $availableYears = $groupedRecords->keys()->values();

        return view('landing.transparency.records', [
            'type' => $typeEnum,
            'groupedRecords' => $groupedRecords,
            'availableYears' => $availableYears,
            'syndicate' => config('syndicate'),
        ]);
    }

    /**
     * Group a single fiscal year's records by period (Financiero) or Spanish month (all other categories).
     *
     * @param  Collection<int, TransparencyRecord>  $yearRecords
     * @return Collection<string, Collection<int, TransparencyRecord>>
     */
    private function groupYearRecords(Collection $yearRecords, TransparencyRecordType $type): Collection
    {
        if ($type->usesPeriod()) {
            return $yearRecords
                ->groupBy(fn (TransparencyRecord $record): string => $record->period ?: 'Sin periodo')
                ->sortBy(function (Collection $items, string $period): array {
                    $index = array_search($period, self::PERIOD_ORDER, true);

                    return [$index === false ? PHP_INT_MAX : $index, $period];
                })
                ->map(fn (Collection $items): Collection => $items->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values());
        }

        $monthOrder = array_flip(self::SPANISH_MONTHS);

        return $yearRecords
            ->groupBy(fn (TransparencyRecord $record): string => $record->document_date !== null
                ? self::SPANISH_MONTHS[(int) $record->document_date->month]
                : 'Sin fecha')
            ->sortByDesc(fn (Collection $items, string $month): int => $monthOrder[$month] ?? 0)
            ->map(fn (Collection $items): Collection => $items->sort(
                fn (TransparencyRecord $a, TransparencyRecord $b): int => ($b->document_date?->timestamp ?? 0) <=> ($a->document_date?->timestamp ?? 0)
                    ?: strcasecmp($a->name, $b->name)
            )->values());
    }

    /**
     * Securely download a media file belonging to a public transparency record.
     */
    public function download(TransparencyRecord $record, Media $media): BinaryFileResponse
    {
        if (! $record->isPublic()
            || $media->model_type !== TransparencyRecord::class
            || (int) $media->model_id !== (int) $record->getKey()
            || $media->collection_name !== 'documents') {
            abort(403, 'Acceso denegado: este documento no está disponible para descarga pública.');
        }

        $path = $media->getPath();

        if (! is_file($path)) {
            abort(404, 'Archivo no encontrado.');
        }

        $downloadName = $media->file_name;

        if ($record->getMedia('documents')->count() === 1) {
            $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
            $slugName = Str::slug($record->name) ?: 'documento';
            $downloadName = $extension !== '' ? "{$slugName}.{$extension}" : $slugName;
        }

        return response()->download($path, $downloadName);
    }
}
