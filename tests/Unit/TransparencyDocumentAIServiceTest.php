<?php

use App\Enums\TransparencyRecordType;
use App\Models\TransparencyRecord;
use App\Services\TransparencyDocumentAIService;
use App\Support\TransparencyAiMetadataResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

    Config::set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1beta/openai/',
        'api_key' => 'test-secret-key',
        'model' => 'gemini-test-model',
        'timeout' => 30,
        'sync_timeout' => 15,
        'max_tokens' => 1024,
        'reasoning_effort' => 'low',
        'max_dimension' => 1000,
        'jpeg_quality' => 70,
        'json_mode' => true,
        'throttle_per_hour' => 10,
        'daily_cap' => 300,
        'headers' => ['X-Title' => 'SIUT-ITSM'],
    ]);
});

function buildDigitalTextPdf(string $text): string
{
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $stream = "BT\n/F1 12 Tf\n72 720 Td\n({$escaped}) Tj\nET\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        5 => '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream",
    ];

    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
    $pdf .= "0000000000 65535 f \n";

    foreach ($objects as $id => $body) {
        $pdf .= str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT)." 00000 n \n";
    }

    $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

    return $pdf;
}

function buildPngBytes(int $width = 2200, int $height = 1400): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);
    $bytes = (string) ob_get_clean();
    imagedestroy($image);

    return $bytes;
}

it('extracts text from a digital PDF capped at 3000 chars and sends only text parts without images', function () {
    $longBody = 'Oficio circular emitido por la Secretaria General del SIUT-ITSM dirigido a la Direccion General '
        .'con folio SIUT-ITSM/045/2025 el 14 de marzo de 2025 sobre gestion de prestaciones sindicales. '
        .str_repeat('Detalle administrativo adicional del expediente sindical. ', 80);

    $pdfBytes = buildDigitalTextPdf($longBody);

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio circular de la Secretaría General sobre gestión de prestaciones sindicales',
                        'summary' => 'Oficio emitido por la Secretaría General del SIUT-ITSM dirigido a la Dirección General respecto a prestaciones sindicales.',
                        'document_date' => '2025-03-14',
                        'fiscal_year' => null,
                        'period' => null,
                        'reference_number' => 'SIUT-ITSM/045/2025',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.94,
                    ]),
                ],
            ]],
            'usage' => [
                'prompt_tokens' => 310,
                'completion_tokens' => 85,
            ],
        ], 200),
    ]);

    $service = app(TransparencyDocumentAIService::class);
    $result = $service->analyzeBytes($pdfBytes, 'application/pdf', 'oficio-045.pdf');

    expect($result)->toBeInstanceOf(TransparencyAiMetadataResult::class)
        ->and($result->successful)->toBeTrue()
        ->and($result->type)->toBe(TransparencyRecordType::Oficios)
        ->and($result->name)->toBe('Oficio circular de la Secretaría General sobre gestión de prestaciones sindicales')
        ->and($result->summary)->toContain('Secretaría General')
        ->and($result->documentDate)->toBe('2025-03-14')
        ->and($result->fiscalYear)->toBeNull()
        ->and($result->period)->toBeNull()
        ->and($result->referenceNumber)->toBe('SIUT-ITSM/045/2025')
        ->and($result->containsPersonalData)->toBeFalse()
        ->and($result->internalReasonSuggestion)->toBeNull()
        ->and($result->confidence)->toBe(0.94)
        ->and($result->sourceKind)->toBe('text');

    Http::assertSent(function (Request $request): bool {
        $userContent = $request['messages'][1]['content'] ?? [];
        $text = (string) ($userContent[0]['text'] ?? '');

        return $request->url() === 'https://ai.test/v1beta/openai/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-secret-key')
            && $request->hasHeader('X-Title', 'SIUT-ITSM')
            && $request['model'] === 'gemini-test-model'
            && $request['reasoning_effort'] === 'low'
            && $request['response_format'] === ['type' => 'json_object']
            && count($userContent) === 1
            && ($userContent[0]['type'] ?? null) === 'text'
            && str_contains($text, 'SIUT-ITSM/045/2025')
            && mb_strlen($text) <= 3300;
    });
});

it('optimizes images to JPEG at most 1000px and normalizes financiero closed periods and personal data flags', function () {
    $pngBytes = buildPngBytes(2200, 1400);

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => "```json\n".json_encode([
                        'type' => 'financiero',
                        'name' => 'ESTADO DE CUENTA BANCARIO DEL PRIMER TRIMESTRE 2025',
                        'summary' => 'Reporte trimestral de ingresos y egresos sindicales correspondiente al periodo.',
                        'document_date' => '2025-03-31',
                        'fiscal_year' => 2025,
                        'period' => 'Primer Trimestre',
                        'reference_number' => 'FIN-999',
                        'contains_personal_data' => true,
                        'internal_reason_suggestion' => 'Contiene datos bancarios y RFC de agremiados.',
                        'confidence' => 92,
                    ])."\n```",
                ],
            ]],
        ], 200),
    ]);

    $result = app(TransparencyDocumentAIService::class)->analyzeBytes($pngBytes, 'image/png', 'estado-q1.png');

    expect($result->successful)->toBeTrue()
        ->and($result->type)->toBe(TransparencyRecordType::Financiero)
        ->and($result->name)->toBe('Estado de cuenta bancario del primer trimestre 2025')
        ->and($result->fiscalYear)->toBe(2025)
        ->and($result->period)->toBe('1er Trimestre')
        ->and($result->documentDate)->toBeNull()
        ->and($result->referenceNumber)->toBeNull()
        ->and($result->containsPersonalData)->toBeTrue()
        ->and($result->internalReasonSuggestion)->toBe('Contiene datos bancarios y RFC de agremiados.')
        ->and($result->confidence)->toBe(0.92)
        ->and($result->sourceKind)->toBe('image');

    Http::assertSent(function (Request $request): bool {
        $parts = $request['messages'][1]['content'] ?? [];
        $dataUrl = (string) ($parts[1]['image_url']['url'] ?? '');

        if (! str_starts_with($dataUrl, 'data:image/jpeg;base64,')) {
            return false;
        }

        $decoded = base64_decode(substr($dataUrl, strlen('data:image/jpeg;base64,')), true);
        if (! is_string($decoded)) {
            return false;
        }

        [$width, $height] = getimagesizefromstring($decoded);

        return count($parts) === 2
            && max($width, $height) === 1000;
    });
});

it('drops out-of-catalog periods and clears period and folio when category is otros', function () {
    $pdfA = buildDigitalTextPdf(str_repeat('Estado financiero anual del sindicato para transparencia sindical. ', 3));
    $pdfB = buildDigitalTextPdf(str_repeat('Documento informativo de historia sindical del instituto tecnologico. ', 3));

    Http::fakeSequence('ai.test/*')
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'financiero',
                        'name' => 'Presupuesto operativo 2024',
                        'summary' => 'Presupuesto general del sindicato.',
                        'fiscal_year' => 2024,
                        'period' => 'Bimestre Inventado',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.85,
                    ]),
                ],
            ]],
        ], 200)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'otros',
                        'name' => 'Memoria historica sindical',
                        'summary' => 'Documento informativo general sin folio aplicable.',
                        'document_date' => '2023-11-10',
                        'fiscal_year' => 2023,
                        'period' => 'Anual',
                        'reference_number' => 'OTROS-001',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.88,
                    ]),
                ],
            ]],
        ], 200);

    $service = app(TransparencyDocumentAIService::class);

    $finResult = $service->analyzeBytes($pdfA, 'application/pdf', 'presupuesto.pdf');
    expect($finResult->successful)->toBeTrue()
        ->and($finResult->type)->toBe(TransparencyRecordType::Financiero)
        ->and($finResult->fiscalYear)->toBe(2024)
        ->and($finResult->period)->toBeNull();

    $otrosResult = $service->analyzeBytes($pdfB, 'application/pdf', 'memoria.pdf');
    expect($otrosResult->successful)->toBeTrue()
        ->and($otrosResult->type)->toBe(TransparencyRecordType::Otros)
        ->and($otrosResult->documentDate)->toBe('2023-11-10')
        ->and($otrosResult->fiscalYear)->toBeNull()
        ->and($otrosResult->period)->toBeNull()
        ->and($otrosResult->referenceNumber)->toBeNull();
});

it('caches successful results by SHA-256 for 24 hours and allows over 10 consecutive analyses without rate limiting', function () {
    $pdfBytes = buildDigitalTextPdf(str_repeat('Acta de asamblea ordinaria del SIUT-ITSM celebrada en mayo 2025. ', 3));

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'actas',
                        'name' => 'Acta de asamblea ordinaria de mayo 2025',
                        'summary' => 'Minuta oficial de la asamblea general ordinaria.',
                        'document_date' => '2025-05-20',
                        'reference_number' => 'ACTA-2025-05',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.91,
                    ]),
                ],
            ]],
        ], 200),
    ]);

    $service = app(TransparencyDocumentAIService::class);

    $first = $service->analyzeBytes($pdfBytes, 'application/pdf', 'acta.pdf');
    $second = $service->analyzeBytes($pdfBytes, 'application/pdf', 'acta-copia.pdf');

    expect($first->successful)->toBeTrue()
        ->and($second->successful)->toBeTrue()
        ->and($second->referenceNumber)->toBe('ACTA-2025-05');

    Http::assertSentCount(1);

    for ($i = 1; $i <= 12; $i++) {
        $distinctPdf = buildDigitalTextPdf("Oficio consecutivo numero {$i} de gestion sindical del SIUT-ITSM emitido en 2025.");
        $res = $service->analyzeBytes($distinctPdf, 'application/pdf', "oficio-{$i}.pdf");
        expect($res->successful)->toBeTrue();
    }

    Http::assertSentCount(13);
});

it('degrades gracefully on disabled AI, HTTP 429/500 and malformed JSON without caching failures', function () {
    $pdfBytes = buildDigitalTextPdf(str_repeat('Oficio sindical de prueba para validacion de resiliencia ante fallos. ', 2));
    $service = app(TransparencyDocumentAIService::class);

    Config::set('services.document_ai.enabled', false);
    $disabled = $service->analyzeBytes($pdfBytes, 'application/pdf', 'oficio.pdf');
    expect($disabled->successful)->toBeFalse()
        ->and($disabled->errorMessage)->toContain('desactivado');

    Config::set('services.document_ai.enabled', true);

    Http::fakeSequence('ai.test/*')
        ->push(['error' => ['message' => 'Rate limited']], 429)
        ->push(['error' => ['message' => 'Internal error']], 500)
        ->push(['choices' => [['message' => ['content' => '{"type": "oficios", "name": "Incompleto']]]], 200)
        ->push([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio recuperado tras fallo',
                        'summary' => 'Resumen valido tras recuperacion.',
                        'document_date' => '2025-04-01',
                        'reference_number' => 'SIUT-REC-01',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.9,
                    ]),
                ],
            ]],
        ], 200);

    $rateLimited = $service->analyzeBytes($pdfBytes, 'application/pdf', 'oficio.pdf');
    expect($rateLimited->successful)->toBeFalse()
        ->and($rateLimited->errorMessage)->not->toBeEmpty();

    $serverError = $service->analyzeBytes($pdfBytes, 'application/pdf', 'oficio.pdf');
    expect($serverError->successful)->toBeFalse()
        ->and($serverError->errorMessage)->not->toBeEmpty();

    $badJson = $service->analyzeBytes($pdfBytes, 'application/pdf', 'oficio.pdf');
    expect($badJson->successful)->toBeFalse();

    $recovered = $service->analyzeBytes($pdfBytes, 'application/pdf', 'oficio.pdf');
    expect($recovered->successful)->toBeTrue()
        ->and($recovered->referenceNumber)->toBe('SIUT-REC-01');
});

it('degrades gracefully on connection timeout without caching the failure', function () {
    $pdfBytes = buildDigitalTextPdf(str_repeat('Oficio sindical de prueba para timeout de conexion con proveedor. ', 2));
    $service = app(TransparencyDocumentAIService::class);

    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    $timedOut = $service->analyzeBytes($pdfBytes, 'application/pdf', 'oficio-timeout.pdf');
    expect($timedOut->successful)->toBeFalse()
        ->and($timedOut->errorMessage)->toContain('conectar');
});

it('logs only technical metrics without PII or document content', function () {
    $sensitiveCurp = 'CURPPRIVADO1234567';
    $sensitiveName = 'Juan Perez Trabajador';
    $pdfBytes = buildDigitalTextPdf("Oficio confidencial dirigido a {$sensitiveName} con identificador {$sensitiveCurp} en 2025.");

    $loggedInfo = [];
    Log::listen(function ($entry) use (&$loggedInfo): void {
        if ($entry->level === 'info') {
            $loggedInfo[] = ['message' => $entry->message, 'context' => $entry->context];
        }
    });

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'oficios',
                        'name' => 'Oficio sindical para Juan Perez',
                        'summary' => 'Gestion sindical confidencial.',
                        'document_date' => '2025-06-01',
                        'reference_number' => 'SIUT-PRIV-09',
                        'contains_personal_data' => true,
                        'internal_reason_suggestion' => 'Contiene CURP del trabajador.',
                        'confidence' => 0.9,
                    ]),
                ],
            ]],
            'usage' => ['prompt_tokens' => 200, 'completion_tokens' => 60],
        ], 200),
    ]);

    $service = app(TransparencyDocumentAIService::class);
    $service->analyzeBytes($pdfBytes, 'application/pdf', 'privado.pdf');

    expect($loggedInfo)->toHaveCount(1);
    $serialized = json_encode($loggedInfo[0]);

    expect($loggedInfo[0]['context'])->toMatchArray([
        'model' => 'gemini-test-model',
        'type' => 'oficios',
        'source_kind' => 'text',
        'cache_hit' => false,
        'prompt_tokens' => 200,
        'completion_tokens' => 60,
    ])
        ->and($loggedInfo[0]['context'])->toHaveKey('elapsed_ms')
        ->and($serialized)->not->toContain($sensitiveCurp)
        ->and($serialized)->not->toContain($sensitiveName)
        ->and($serialized)->not->toContain('SIUT-PRIV-09')
        ->and($serialized)->not->toContain('Contiene CURP');
});

it('analyzes stored Spatie Media files and degrades gracefully if the physical file is missing', function () {
    Storage::fake('public');

    $pdfContent = buildDigitalTextPdf(str_repeat('Convenio colectivo de trabajo del SIUT-ITSM con folio LEG-2025-01 emitido en 2025. ', 2));
    $record = TransparencyRecord::factory()->create();
    $media = $record->addMedia(UploadedFile::fake()->createWithContent('convenio.pdf', $pdfContent))
        ->toMediaCollection(TransparencyRecord::DOCUMENTS_COLLECTION);

    Http::fake([
        'ai.test/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'type' => 'legal',
                        'name' => 'Convenio colectivo de trabajo 2025',
                        'summary' => 'Convenio colectivo celebrado entre el SIUT-ITSM y la dirección.',
                        'document_date' => '2025-01-15',
                        'reference_number' => 'LEG-2025-01',
                        'contains_personal_data' => false,
                        'internal_reason_suggestion' => '',
                        'confidence' => 0.95,
                    ]),
                ],
            ]],
        ], 200),
    ]);

    $service = app(TransparencyDocumentAIService::class);
    $result = $service->analyzeMedia($media);

    expect($result->successful)->toBeTrue()
        ->and($result->type)->toBe(TransparencyRecordType::Legal)
        ->and($result->referenceNumber)->toBe('LEG-2025-01');

    Storage::disk($media->disk)->delete($media->getPathRelativeToRoot());
    $missing = $service->analyzeMedia($media);

    expect($missing->successful)->toBeFalse()
        ->and($missing->errorMessage)->toContain('no se encontró');
});
