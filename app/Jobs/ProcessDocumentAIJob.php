<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Exceptions\DocumentAIException;
use App\Models\User;
use App\Services\DocumentAIService;
use App\Support\DocumentAIResult;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Validates an uploaded user document with the AI provider.
 *
 *  - Valid document    → stores `extracted_data` and leaves it "Pendiente de Revisión" for a human.
 *  - Invalid document  → deletes the file + media record and notifies the owner in the Filament panel.
 *  - AI unavailable    → never rejects; the document goes to manual review.
 */
class ProcessDocumentAIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Keep `queue.connections.database.retry_after` higher than `$timeout`. */
    public int $timeout = 120;

    public int $tries = 3;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Media $media) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(DocumentAIService $service): void
    {
        $media = $this->freshMedia();

        if ($media === null) {
            return; // File was replaced or removed while queued.
        }

        $type = UserDocumentType::tryFrom((string) $media->collection_name);
        $owner = $media->model;

        if ($type === null || ! $owner instanceof User) {
            Log::warning('ProcessDocumentAIJob: el medio no pertenece a un documento de usuario.', [
                'media_id' => $media->id,
                'collection' => $media->collection_name,
            ]);

            return;
        }

        if (! $this->isAwaitingAI($media)) {
            return; // Already analyzed or already decided by a human reviewer.
        }

        $this->updateMedia($media, ['status' => DocumentStatus::Processing->value]);

        try {
            $result = $service->analyze($media, $type);
        } catch (DocumentAIException $e) {
            if ($e->retryable) {
                throw $e; // Let the queue retry with backoff; failed() handles the last attempt.
            }

            $this->releaseToManualReview($media, $e->getMessage());

            return;
        }

        $media = $this->freshMedia();

        if ($media === null || $media->getCustomProperty('status') !== DocumentStatus::Processing->value) {
            return; // Replaced or reviewed by a human while the AI was working.
        }

        if ($result->isValidDocument) {
            $this->markReadyForReview($media, $result);

            return;
        }

        // Deleting a user's file is destructive: only do it when the AI is confident.
        if ($result->confidenceScore < (float) config('services.document_ai.min_reject_confidence', 0.7)) {
            $this->markReadyForReview($media, $result, flaggedAsDoubtful: true);

            return;
        }

        $this->rejectAndClean($media, $owner, $type, $result);
    }

    /**
     * Last-resort handler once all retries are exhausted: never lose the document.
     */
    public function failed(Throwable $exception): void
    {
        $media = $this->freshMedia();

        if ($media === null) {
            return;
        }

        $this->releaseToManualReview($media, $exception->getMessage());
    }

    private function markReadyForReview(Media $media, DocumentAIResult $result, bool $flaggedAsDoubtful = false): void
    {
        $this->updateMedia($media, [
            'status' => DocumentStatus::Pending->value,
            'extracted_data' => $result->toArray(),
            'ai_processed_at' => now()->toIso8601String(),
            'ai_flagged' => $flaggedAsDoubtful,
        ]);

        Log::info('Documento analizado por IA y enviado a revisión.', [
            'media_id' => $media->id,
            'collection' => $media->collection_name,
            'confidence' => $result->confidenceScore,
            'flagged' => $flaggedAsDoubtful,
        ]);
    }

    private function releaseToManualReview(Media $media, string $reason): void
    {
        $this->updateMedia($media, [
            'status' => DocumentStatus::Pending->value,
            'ai_error' => Str::limit($reason, 200),
            'ai_processed_at' => now()->toIso8601String(),
        ]);

        Log::error('La IA no pudo analizar el documento; queda para revisión manual.', [
            'media_id' => $media->id,
            'collection' => $media->collection_name,
            'reason' => Str::limit($reason, 200),
        ]);
    }

    private function rejectAndClean(Media $media, User $owner, UserDocumentType $type, DocumentAIResult $result): void
    {
        $disk = $media->disk;
        $path = $media->getPathRelativeToRoot();
        $mediaId = $media->id;

        $reason = $result->observations !== ''
            ? $result->observations
            : 'El archivo no corresponde al documento solicitado o no es legible.';

        DB::transaction(function () use ($media, $owner, $type, $disk, $path, $reason): void {
            Storage::disk($disk)->delete($path);

            // Removes the DB record (and any leftover media directory) so the slot shows as "sin subir".
            $media->delete();

            DB::afterCommit(function () use ($owner, $type, $reason): void {
                Notification::make()
                    ->title("Documento rechazado: {$type->getLabel()}")
                    ->body("Tu archivo fue eliminado automáticamente. Motivo: {$reason} Por favor sube un nuevo archivo correcto desde tu perfil.")
                    ->danger()
                    ->sendToDatabase($owner);
            });
        });

        Log::info('Documento rechazado por IA y eliminado.', [
            'media_id' => $mediaId,
            'user_id' => $owner->id,
            'collection' => $type->value,
            'confidence' => $result->confidenceScore,
        ]);
    }

    /**
     * Only documents that have not been analyzed nor reviewed by a human.
     */
    private function isAwaitingAI(Media $media): bool
    {
        $status = $media->getCustomProperty('status');

        return in_array($status, [null, DocumentStatus::Pending->value, DocumentStatus::Processing->value], true)
            && ! $media->hasCustomProperty('ai_processed_at');
    }

    private function freshMedia(): ?Media
    {
        return Media::query()->find($this->media->getKey());
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function updateMedia(Media $media, array $properties): void
    {
        foreach ($properties as $key => $value) {
            $media->setCustomProperty($key, $value);
        }

        $media->save();
    }
}
