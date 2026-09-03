<?php

namespace App\Http\Controllers;

use App\Enums\TransparencyRecordStatus;
use App\Enums\TransparencyRecordType;
use App\Models\TransparencyDocument;
use App\Models\TransparencyRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
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

        // Fetch all transparency records of the specified type.
        // We fetch ALL records regardless of status because the user requested:
        // "se van a mostrar todas pero no se podran acceder a las que no sean publicas"
        $records = TransparencyRecord::query()
            ->where('type', $typeEnum)
            ->with(['documents' => function ($query) {
                $query->orderBy('published_at', 'desc')->orderBy('name', 'asc');
            }])
            ->orderBy('fiscal_year', 'desc')
            ->orderBy('period', 'asc')
            ->orderBy('name', 'asc')
            ->get();

        // Group records by fiscal_year and then by period
        $groupedRecords = $records->groupBy('fiscal_year')->map(function ($yearRecords) {
            return $yearRecords->groupBy('period');
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
     * Securely download a public document of a public transparency record.
     */
    public function download(TransparencyDocument $document): BinaryFileResponse
    {
        // Enforce safety checks:
        // 1. The document must be public (is_public = true)
        // 2. The associated record must be published (status = TransparencyRecordStatus::Publicado)
        if (! $document->is_public || ! $document->record || ! $document->record->isPublished()) {
            abort(403, 'Acceso denegado: este documento no es público o el registro asociado no ha sido publicado.');
        }

        $media = $document->getFirstMedia('file');

        if ($media === null) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->download($media->getPath(), $media->file_name);
    }
}
