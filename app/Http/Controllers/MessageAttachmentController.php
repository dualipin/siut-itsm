<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MessageAttachmentController extends Controller
{
    /**
     * Download an attachment safely ensuring only participants can access it.
     */
    public function download(Request $request, Media $media): BinaryFileResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Verify media belongs to a ConversationMessage
        if ($media->model_type !== ConversationMessage::class) {
            abort(404);
        }

        /** @var ConversationMessage|null $message */
        $message = ConversationMessage::with('conversation')->find($media->model_id);

        if (! $message || ! $message->conversation) {
            abort(404);
        }

        /** @var Conversation $conversation */
        $conversation = $message->conversation;

        // Verify user is a participant of the conversation
        if (! $conversation->hasParticipant($user->id)) {
            abort(403, 'No tienes permiso para acceder a este archivo.');
        }

        return response()->download($media->getPath(), $media->file_name);
    }
}
