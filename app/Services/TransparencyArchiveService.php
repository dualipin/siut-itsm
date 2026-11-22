<?php

namespace App\Services;

use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TransparencyArchiveService
{
    /**
     * Spanish month names keyed by calendar month number (1-12).
     *
     * @var array<int, string>
     */
    private const array SPANISH_MONTHS = [
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
     * Fetch all transparency records for a category grouped by fiscal year and period or Spanish month.
     *
     * @return Collection<int|string, Collection<string, Collection<int, TransparencyRecord>>>
     */
    public function groupedRecordsForType(TransparencyRecordType $type): Collection
    {
        // ADR-0001: list both Publico and Interno records; download links render only for Publico.
        $records = TransparencyRecord::query()
            ->where('type', $type)
            ->with('media')
            ->orderBy('fiscal_year', 'desc')
            ->orderBy('document_date', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return $records
            ->groupBy('fiscal_year')
            ->sortKeysDesc()
            ->map(fn (Collection $yearRecords): Collection => $this->groupYearRecords($yearRecords, $type));
    }

    /**
     * Resolve the public download filename for a media item belonging to a transparency record.
     */
    public function resolvePublicDownloadFilename(TransparencyRecord $record, Media $media): string
    {
        if ($record->getMedia(TransparencyRecord::DOCUMENTS_COLLECTION)->count() === 1) {
            $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
            $slugName = Str::slug($record->name) ?: 'documento';

            return $extension !== '' ? "{$slugName}.{$extension}" : $slugName;
        }

        return $media->file_name;
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
            $periodOrder = array_keys(TransparencyRecordType::periodOptions());

            return $yearRecords
                ->groupBy(fn (TransparencyRecord $record): string => $record->period ?: 'Sin periodo')
                ->sortBy(function (Collection $items, string $period) use ($periodOrder): array {
                    $index = array_search($period, $periodOrder, true);

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
}
