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
        'sync_timeout' => 5,
        'max_tokens' => 512,
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'store_max_dimension' => 1600,
        'store_jpeg_quality' => 80,
        'json_mode' => true,
        'min_reject_confidence' => 0.7,
        'throttle_per_hour' => 100,
        'daily_cap' => 9999,
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

// ── extractIne ────────────────────────────────────────────────────────────────

function ineJson(array $overrides = []): string
{
    return json_encode(array_replace([
        'is_valid_document' => true,
        'document_type' => 'ine',
        'extracted_fields' => [
            'nombre' => 'CARLOS',
            'apellidos' => 'MENDOZA LOPEZ',
            'curp' => 'MELC950515HDFRRN09',
            'fecha_nacimiento' => '1995-05-15',
            'domicilio' => 'Reforma 123, Col. Centro',
            'vigencia' => '2030',
        ],
        'confidence_score' => 0.95,
        'observations' => '',
    ], $overrides));
}

function payslipJson(array $overrides = []): string
{
    return json_encode(array_replace([
        'is_valid_document' => true,
        'document_type' => 'comprobante_pago',
        'extracted_fields' => [
            'nombre' => 'CARLOS MENDOZA LOPEZ',
            'nss' => '12345678901',
            'categoria' => 'Técnico Operativo',
            'salario' => '8500.00',
            'periodo' => '01/10/2024 al 15/10/2024',
            'fecha_pago' => '2024-10-15',
        ],
        'confidence_score' => 0.92,
        'observations' => '',
    ], $overrides));
}

test('extractIne returns extended fields including apellidos, vigencia and domicilio', function () {
    Http::fake(['ai.test/*' => aiCompletion(ineJson())]);

    $result = app(DocumentAIService::class)->extractIne(fakePngBytes(800, 500), 'image/png');

    expect($result->isValidDocument)->toBeTrue()
        ->and($result->documentType)->toBe('ine')
        ->and($result->extractedFields['curp'])->toBe('MELC950515HDFRRN09')
        ->and($result->extractedFields['apellidos'])->toBe('MENDOZA LOPEZ')
        ->and($result->extractedFields['vigencia'])->toBe('2030')
        ->and($result->confidenceScore)->toBe(0.95);
});

test('extractIne uses the dedicated INE system prompt (not the generic one)', function () {
    Http::fake(['ai.test/*' => aiCompletion(ineJson())]);

    app(DocumentAIService::class)->extractIne(fakePngBytes(300, 200), 'image/png');

    Http::assertSent(function (Request $request): bool {
        $systemContent = $request['messages'][0]['content'];

        // INE prompt should mention "vigencia" and NOT the generic "{EXPECTED_DESCRIPTION}" placeholder
        return str_contains($systemContent, 'vigencia')
            && ! str_contains($systemContent, '{EXPECTED_DESCRIPTION}');
    });
});

test('extractPayslip returns extended fields including nss, categoria, salario, fecha_pago', function () {
    Http::fake(['ai.test/*' => aiCompletion(payslipJson())]);

    $result = app(DocumentAIService::class)->extractPayslip(fakePngBytes(800, 600), 'image/png');

    expect($result->isValidDocument)->toBeTrue()
        ->and($result->documentType)->toBe('comprobante_pago')
        ->and($result->extractedFields['nss'])->toBe('12345678901')
        ->and($result->extractedFields['salario'])->toBe('8500.00')
        ->and($result->extractedFields['fecha_pago'])->toBe('2024-10-15')
        ->and($result->confidenceScore)->toBe(0.92);
});

test('extractPayslip uses the dedicated payslip system prompt', function () {
    Http::fake(['ai.test/*' => aiCompletion(payslipJson())]);

    app(DocumentAIService::class)->extractPayslip(fakePngBytes(300, 200), 'image/png');

    Http::assertSent(function (Request $request): bool {
        $systemContent = $request['messages'][0]['content'];

        return str_contains($systemContent, 'nómina')
            && str_contains($systemContent, 'fecha_pago')
            && ! str_contains($systemContent, '{EXPECTED_DESCRIPTION}');
    });
});

// ── review() ──────────────────────────────────────────────────────────────────

test('review() returns null result when AI is disabled', function () {
    config()->set('services.document_ai.enabled', false);
    Http::fake();

    $result = app(DocumentAIService::class)->review(fakePngBytes(300, 200), 'image/png', UserDocumentType::Ine, 'ip:1.2.3.4');

    expect($result['result'])->toBeNull()
        ->and($result['warnings'])->toBeEmpty();

    Http::assertNothingSent();
});

test('review() routes INE to extractIne and returns extended fields', function () {
    Http::fake(['ai.test/*' => aiCompletion(ineJson())]);

    $result = app(DocumentAIService::class)->review(fakePngBytes(300, 200), 'image/png', UserDocumentType::Ine, 'ip:1.2.3.4');

    expect($result['result'])->not->toBeNull()
        ->and($result['result']->extractedFields['apellidos'])->toBe('MENDOZA LOPEZ')
        ->and($result['warnings'])->toBeEmpty();
});

test('review() routes ComprobantePago to extractPayslip and returns extended fields', function () {
    Http::fake(['ai.test/*' => aiCompletion(payslipJson())]);

    $result = app(DocumentAIService::class)->review(fakePngBytes(300, 200), 'image/png', UserDocumentType::ComprobantePago, 'ip:1.2.3.4');

    expect($result['result'])->not->toBeNull()
        ->and($result['result']->extractedFields['salario'])->toBe('8500.00')
        ->and($result['warnings'])->toBeEmpty();
});

test('review() degrades gracefully when the AI call fails', function () {
    Http::fake(['ai.test/*' => Http::response(['error' => ['message' => 'service unavailable']], 503)]);

    $result = app(DocumentAIService::class)->review(fakePngBytes(300, 200), 'image/png', UserDocumentType::Ine, 'ip:1.2.3.4');

    expect($result['result'])->toBeNull()
        ->and($result['warnings'])->not->toBeEmpty();
});

// ── normalizeForStorage ───────────────────────────────────────────────────────

test('normalizeForStorage produces a JPEG no wider than 1600px', function () {
    $jpeg = app(DocumentAIService::class)->normalizeForStorage(fakePngBytes(3000, 2000));

    [$width, $height] = getimagesizefromstring($jpeg);

    expect(max($width, $height))->toBeLessThanOrEqual(1600);
});
