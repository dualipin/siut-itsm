<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProcessImportBatchRequest;
use App\Http\Requests\StoreImportChunkRequest;
use App\Services\UserBulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserBulkImportController extends Controller
{
    public function __construct(
        protected readonly UserBulkImportService $importService,
    ) {}

    public function storeChunk(StoreImportChunkRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->importService->storeChunk(
            batchId: $validated['batch_id'],
            fileId: $validated['file_id'],
            chunkIndex: (int) $validated['chunk_index'],
            totalChunks: (int) $validated['total_chunks'],
            originalName: $validated['original_name'],
            chunk: $request->file('chunk'),
        );

        return response()->json($result);
    }

    public function processBatch(ProcessImportBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->importService->processBatch(
            batchId: $validated['batch_id'],
            offset: (int) $validated['offset'],
            limit: (int) $validated['limit'],
            fallbackPassword: $validated['fallback_password'],
        );

        return response()->json($result);
    }

    public function cleanup(Request $request, string $batchId): JsonResponse
    {
        if (! ($request->user()?->isLeaderOrAdmin() ?? false)) {
            abort(403);
        }

        $this->importService->cleanup($batchId);

        return response()->json(['cleaned' => true]);
    }
}
