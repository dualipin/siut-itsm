<?php

namespace App\Http\Controllers;

use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use App\Services\TransparencyArchiveService;
use Illuminate\Contracts\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicTransparencyController extends Controller
{
    public function __construct(
        private readonly TransparencyArchiveService $archiveService,
    ) {}

    /**
     * Display a listing of public transparency records for a specific type.
     */
    public function index(string $type): View
    {
        $typeEnum = TransparencyRecordType::fromSlug($type);

        if ($typeEnum === null) {
            abort(404, 'Tipo de registro de transparencia no encontrado.');
        }

        $groupedRecords = $this->archiveService->groupedRecordsForType($typeEnum);

        return view('landing.transparency.records', [
            'type' => $typeEnum,
            'groupedRecords' => $groupedRecords,
            'availableYears' => $groupedRecords->keys()->values(),
            'syndicate' => config('syndicate'),
        ]);
    }

    /**
     * Securely download a media file belonging to a public transparency record.
     */
    public function download(TransparencyRecord $record, Media $media): BinaryFileResponse
    {
        if (! $record->isPublico() || ! $record->ownsDocumentMedia($media)) {
            abort(403, 'Acceso denegado: este documento no está disponible para descarga pública.');
        }

        $path = $media->getPath();

        if (! is_file($path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->download(
            $path,
            $this->archiveService->resolvePublicDownloadFilename($record, $media),
        );
    }
}
