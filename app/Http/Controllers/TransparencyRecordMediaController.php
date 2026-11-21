<?php

namespace App\Http\Controllers;

use App\Models\TransparencyRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransparencyRecordMediaController extends Controller
{
    /**
     * Safely display or download a transparency record media file for active Directiva members.
     */
    public function show(Request $request, TransparencyRecord $record, Media $media): StreamedResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $request->user();

        abort_unless($currentUser !== null, 401);
        abort_unless($currentUser->is_active, 403, 'Tu cuenta se encuentra inactiva.');

        Gate::authorize('view', $record);

        abort_unless(
            $media->model_type === TransparencyRecord::class
                && (int) $media->model_id === (int) $record->getKey()
                && $media->collection_name === 'documents',
            404,
            'El archivo solicitado no pertenece a este registro.'
        );

        if ($request->boolean('download')) {
            return $media->toResponse($request);
        }

        return $media->toInlineResponse($request);
    }
}
