<?php

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Exceptions\DocumentAIException;
use App\Jobs\ProcessDocumentAIJob;
use App\Models\User;
use App\Services\DocumentAIService;
use App\Support\DocumentAIResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

function aiResult(bool $valid, float $confidence = 0.95, string $observations = ''): DocumentAIResult
{
    return new DocumentAIResult(
        isValidDocument: $valid,
        documentType: $valid ? 'ine' : 'otro',
        extractedFields: ['nombre' => 'JUAN PEREZ LOPEZ', 'curp' => 'PELJ800101HDFRPN09', 'monto' => ''],
        confidenceScore: $confidence,
        observations: $observations,
    );
}

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create(['role' => UserRole::Agremiado]);

    $this->media = $this->user
        ->addMedia(UploadedFile::fake()->createWithContent('ine.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"))
        ->toMediaCollection(UserDocumentType::Ine->value);

    $this->filePath = $this->media->getPathRelativeToRoot();
});

test('valid document stores extracted data and stays pending for human review', function () {
    $this->mock(DocumentAIService::class)
        ->shouldReceive('analyze')->once()->andReturn(aiResult(valid: true));

    ProcessDocumentAIJob::dispatchSync($this->media);

    $media = $this->media->fresh();

    expect($media->getCustomProperty('status'))->toBe(DocumentStatus::Pending->value)
        ->and($media->getCustomProperty('extracted_data.is_valid_document'))->toBeTrue()
        ->and($media->getCustomProperty('extracted_data.extracted_fields.curp'))->toBe('PELJ800101HDFRPN09')
        ->and($media->getCustomProperty('ai_processed_at'))->not->toBeNull();

    Storage::disk('local')->assertExists($this->filePath);
    expect($this->user->notifications()->count())->toBe(0);
});

test('invalid document is deleted from disk and database and the owner is notified', function () {
    $reason = 'La imagen es un meme y no una credencial INE.';

    $this->mock(DocumentAIService::class)
        ->shouldReceive('analyze')->once()->andReturn(aiResult(valid: false, observations: $reason));

    ProcessDocumentAIJob::dispatchSync($this->media);

    Storage::disk('local')->assertMissing($this->filePath);
    expect(Media::query()->find($this->media->id))->toBeNull();

    $notification = $this->user->notifications()->latest()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['title'])->toContain('INE')
        ->and($notification->data['body'])->toContain($reason);
});

test('invalid verdict with low confidence is kept for manual review instead of deleted', function () {
    $this->mock(DocumentAIService::class)
        ->shouldReceive('analyze')->once()->andReturn(aiResult(valid: false, confidence: 0.3, observations: 'Foto oscura.'));

    ProcessDocumentAIJob::dispatchSync($this->media);

    Storage::disk('local')->assertExists($this->filePath);

    $media = $this->media->fresh();

    expect($media->getCustomProperty('status'))->toBe(DocumentStatus::Pending->value)
        ->and($media->getCustomProperty('ai_flagged'))->toBeTrue();
    expect($this->user->notifications()->count())->toBe(0);
});

test('non retryable AI failure sends the document to manual review without deleting it', function () {
    $this->mock(DocumentAIService::class)
        ->shouldReceive('analyze')->once()->andThrow(DocumentAIException::unprocessable('Imagick no disponible'));

    ProcessDocumentAIJob::dispatchSync($this->media);

    Storage::disk('local')->assertExists($this->filePath);

    $media = $this->media->fresh();

    expect($media->getCustomProperty('status'))->toBe(DocumentStatus::Pending->value)
        ->and($media->getCustomProperty('ai_error'))->toBe('Imagick no disponible');
});

test('retryable AI failure is rethrown so the queue can retry, and failed() keeps the file', function () {
    $this->mock(DocumentAIService::class)
        ->shouldReceive('analyze')->once()->andThrow(DocumentAIException::transient('HTTP 429'));

    expect(fn () => ProcessDocumentAIJob::dispatchSync($this->media))->toThrow(DocumentAIException::class);

    expect($this->media->fresh()->getCustomProperty('status'))->toBe(DocumentStatus::Processing->value);

    (new ProcessDocumentAIJob($this->media))->failed(DocumentAIException::transient('HTTP 429'));

    Storage::disk('local')->assertExists($this->filePath);
    expect($this->media->fresh()->getCustomProperty('status'))->toBe(DocumentStatus::Pending->value);
});

test('documents already reviewed by a human are not sent to the AI', function () {
    $this->media->setCustomProperty('status', DocumentStatus::Valid->value)->save();

    $this->mock(DocumentAIService::class)->shouldNotReceive('analyze');

    ProcessDocumentAIJob::dispatchSync($this->media);

    expect($this->media->fresh()->getCustomProperty('status'))->toBe(DocumentStatus::Valid->value);
});

test('an admin decision made while the AI is working is never overwritten or deleted', function () {
    $this->mock(DocumentAIService::class)
        ->shouldReceive('analyze')->once()->andReturnUsing(function () {
            // Admin approves from review-documents while the request to the AI is in flight.
            Media::query()->find($this->media->id)
                ->setCustomProperty('status', DocumentStatus::Valid->value)
                ->save();

            return aiResult(valid: false, observations: 'Parece un meme.');
        });

    ProcessDocumentAIJob::dispatchSync($this->media);

    Storage::disk('local')->assertExists($this->filePath);
    expect($this->media->fresh()->getCustomProperty('status'))->toBe(DocumentStatus::Valid->value);
    expect($this->user->notifications()->count())->toBe(0);
});

test('a document the admin marked invalid is not reprocessed by the AI', function () {
    $this->media->setCustomProperty('status', DocumentStatus::Invalid->value)->save();

    $this->mock(DocumentAIService::class)->shouldNotReceive('analyze');

    ProcessDocumentAIJob::dispatchSync($this->media);

    expect($this->media->fresh()->getCustomProperty('status'))->toBe(DocumentStatus::Invalid->value);
});

test('job is skipped when the media was replaced before processing', function () {
    $this->media->delete();

    $this->mock(DocumentAIService::class)->shouldNotReceive('analyze');

    ProcessDocumentAIJob::dispatchSync($this->media);

    expect($this->user->notifications()->count())->toBe(0);
});
