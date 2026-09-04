<?php

namespace App\Http\Controllers;

use App\Enums\UserDocumentType;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserDocumentController extends Controller
{
    /**
     * Safely display or download an affiliation PDF document.
     *
     * Only administrators, leaders, or the document owner are authorized.
     */
    public function show(Request $request, User $user, string $type): StreamedResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $request->user();

        abort_unless($currentUser !== null, 401);

        // Enforce authorization: only leaders, administrators, or the owner can view
        abort_unless(
            $currentUser->isLeaderOrAdmin() || $currentUser->id === $user->id,
            403,
            'No tienes autorización para consultar este documento.'
        );

        $documentType = UserDocumentType::tryFrom($type);

        abort_unless($documentType !== null, 404, 'Tipo de documento no válido.');

        $media = $user->getDocumentMedia($documentType);

        abort_unless($media !== null, 404, 'El documento solicitado no ha sido subido.');

        if ($request->boolean('download')) {
            return $media->toResponse($request);
        }

        return $media->toInlineResponse($request);
    }
}
