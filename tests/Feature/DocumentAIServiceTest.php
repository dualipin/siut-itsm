<?php

use App\Enums\UserDocumentType;
use App\Exceptions\DocumentAIException;
use App\Services\DocumentAIService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakePngBytes(int $width = 2000, int $height = 1200): string
{
    $image = imagecreatetruecolor($width, $height);

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function aiCompletion(string $content, int $status = 200)
{
    return Http::response(['choices' => [['message' => ['content' => $content]]]], $status);
}

function aiJson(array $overrides = []): string
{
    return json_encode(array_replace([
        'is_valid_document' => true,
        'document_type' => 'ine',
        'extracted_fields' => ['nombre' => 'Juan Perez', 'curp' => 'pelj800101hdfrpn09', 'monto' => ''],
        'confidence_score' => 0.93,
        'observations' => '',
    ], $overrides));
}

function aiException(callable $callback): DocumentAIException
{
    try {
        $callback();
    } catch (DocumentAIException $e) {
        return $e;
    }

    throw new RuntimeException('Expected DocumentAIException was not thrown.');
}

beforeEach(function () {
    config()->set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'secret-key',
        'model' => 'test-model',
        'timeout' => 5,
        'max_tokens' => 512,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'min_reject_confidence' => 0.7,
        'headers' => ['X-Title' => 'SIUT'],
    ]);
});

test('it resizes the image to 1000px and sends an OpenAI-compatible request', function () {
    Http::fake(['ai.test/*' => aiCompletion(aiJson())]);

    $result = app(DocumentAIService::class)->analyzeBytes(fakePngBytes(), 'image/png', UserDocumentType::Ine);

    expect($result->isValidDocument)->toBeTrue()
        ->and($result->extractedFields['curp'])->toBe('PELJ800101HDFRPN09')
        ->and($result->confidenceScore)->toBe(0.93);

    Http::assertSent(function (Request $request): bool {
        $dataUrl = $request['messages'][1]['content'][1]['image_url']['url'];
        [$width, $height] = getimagesizefromstring(base64_decode(substr($dataUrl, strlen('data:image/jpeg;base64,'))));

        return $request->url() === 'https://ai.test/v1beta/openai/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer secret-key')
            && $request->hasHeader('X-Title', 'SIUT')
            && $request['model'] === 'test-model'
            && $request['response_format'] === ['type' => 'json_object']
            && str_starts_with($dataUrl, 'data:image/jpeg;base64,')
            && max($width, $height) === 1000
            && str_contains($request['messages'][0]['content'], 'ine');
    });
});

test('digital PDF text is sent as a text-only message without any image part', function () {
    Http::fake(['ai.test/*' => aiCompletion(aiJson(['document_type' => 'curp']))]);

    $result = app(DocumentAIService::class)->analyzeText('CLAVE UNICA DE REGISTRO DE POBLACION PELJ800101HDFRPN09', UserDocumentType::Curp);

    expect($result->isValidDocument)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => count($request['messages'][1]['content']) === 1
        && str_contains($request['messages'][1]['content'][0]['text'], 'PELJ800101HDFRPN09'));
});

test('it tolerates markdown fences around the JSON', function () {
    Http::fake(['ai.test/*' => aiCompletion("```json\n".aiJson()."\n```")]);

    $result = app(DocumentAIService::class)->analyzeBytes(fakePngBytes(300, 200), 'image/png', UserDocumentType::Ine);

    expect($result->isValidDocument)->toBeTrue();
});

test('a valid verdict for the wrong document type is treated as invalid', function () {
    Http::fake(['ai.test/*' => aiCompletion(aiJson(['document_type' => 'curp']))]);

    $result = app(DocumentAIService::class)->analyzeBytes(fakePngBytes(300, 200), 'image/png', UserDocumentType::Ine);

    expect($result->isValidDocument)->toBeFalse()
        ->and($result->documentType)->toBe('curp');
});

test('rate limits and server errors are retryable', function (int $status) {
    Http::fake(['ai.test/*' => Http::response(['error' => ['message' => 'slow down']], $status)]);

    $exception = aiException(fn () => app(DocumentAIService::class)->analyzeBytes(fakePngBytes(300, 200), 'image/png', UserDocumentType::Ine));

    expect($exception->retryable)->toBeTrue();
})->with([429, 500, 503]);

test('authentication errors are not retryable', function () {
    Http::fake(['ai.test/*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

    $exception = aiException(fn () => app(DocumentAIService::class)->analyzeBytes(fakePngBytes(300, 200), 'image/png', UserDocumentType::Ine));

    expect($exception->retryable)->toBeFalse();
});

test('a non JSON answer is retryable', function () {
    Http::fake(['ai.test/*' => aiCompletion('Lo siento, no puedo ayudar con eso.')]);

    $exception = aiException(fn () => app(DocumentAIService::class)->analyzeBytes(fakePngBytes(300, 200), 'image/png', UserDocumentType::Ine));

    expect($exception->retryable)->toBeTrue();
});

test('missing configuration is not retryable and no request is made', function () {
    config()->set('services.document_ai.api_key', null);
    Http::fake();

    $exception = aiException(fn () => app(DocumentAIService::class)->analyzeBytes(fakePngBytes(300, 200), 'image/png', UserDocumentType::Ine));

    expect($exception->retryable)->toBeFalse();
    Http::assertNothingSent();
});

test('unreadable files never reach the provider', function () {
    Http::fake();

    $exception = aiException(fn () => app(DocumentAIService::class)->analyzeBytes('not-an-image', 'image/png', UserDocumentType::Ine));

    expect($exception->retryable)->toBeFalse();
    Http::assertNothingSent();
});

test('system prompt embeds the expected type and the mandatory JSON contract', function () {
    $prompt = app(DocumentAIService::class)->systemPrompt(UserDocumentType::ComprobantePago);

    expect($prompt)
        ->toContain('comprobante_pago')
        ->toContain('"is_valid_document": boolean')
        ->toContain('"extracted_fields": {"nombre": string, "curp": string, "monto": string}')
        ->not->toContain('{EXPECTED_TYPE}')
        ->not->toContain('{EXPECTED_DESCRIPTION}');
});
