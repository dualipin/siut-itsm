<?php

namespace App\Http\Controllers;

use App\Models\TransparencyDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransparencyDocumentController extends Controller
{
    /**
     * Safely display or download a transparency document file.
     */
    public function show(Request $request, TransparencyDocument $document): StreamedResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $request->user();

        abort_unless($currentUser !== null, 401);

        // Only active users with portal access can access portal documents
        abort_unless($currentUser->is_active, 403, 'Tu cuenta se encuentra inactiva.');

        $media = $document->getFirstMedia('file');

        abort_unless($media !== null, 404, 'El documento solicitado no cuenta con un archivo adjunto.');

        if ($request->boolean('download')) {
            return $media->toResponse($request);
        }

        return $media->toInlineResponse($request);
    }
}
