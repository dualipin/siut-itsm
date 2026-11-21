<?php

namespace App\Http\Controllers;

use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicTransparencyController extends Controller
{
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
            ->orderBy('period', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        $groupedRecords = $records->groupBy('fiscal_year')->map(function ($yearRecords) {
            return $yearRecords->groupBy(fn (TransparencyRecord $record): string => $record->period ?: 'General');
        });

        $availableYears = $records->pluck('fiscal_year')->unique()->sortDesc()->values();

        return view('landing.transparency.records', [
            'type' => $typeEnum,
            'groupedRecords' => $groupedRecords,
            'availableYears' => $availableYears,
            'syndicate' => config('syndicate'),
        ]);
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
