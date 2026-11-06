<?php

use App\Enums\LoanAiStatus;
use App\Models\User;
use App\Services\LoanDocumentAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->aiService = app(LoanDocumentAIService::class);
});

it('rejects files exceeding 5MB or with disallowed extensions', function () {
    // Disallowed extension (.exe)
    $fileExe = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');
    expect(fn () => $this->aiService->validateLocalFile($fileExe))->toThrow(ValidationException::class);

    // Over 5MB (6MB)
    $fileBig = UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf');
    expect(fn () => $this->aiService->validateLocalFile($fileBig))->toThrow(ValidationException::class);

    // Allowed (valid PNG under 5MB)
    $fileValid = UploadedFile::fake()->image('recibo.png', 800, 600)->size(500);
    $this->aiService->validateLocalFile($fileValid);
    expect(true)->toBeTrue();
});

it('stores file privately and computes sha256 hash', function () {
    $file = UploadedFile::fake()->create('recibo_nomina.pdf', 200, 'application/pdf');

    $result = $this->aiService->storePrivatelyAndHash($file);

    expect($result['path'])->toStartWith('loans/evidences/')
        ->and(strlen($result['hash']))->toBe(64)
        ->and(Storage::disk('local')->exists($result['path']))->toBeTrue();
});

it('evaluates matching payslip as valido when AI responds with high confidence and matching name', function () {
    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');

    $user = User::factory()->create([
        'name' => 'Carlos Alberto',
        'surnames' => 'Mendoza Perez',
        'curp' => 'MEPC850101HTNRR01',
    ]);

    $file = UploadedFile::fake()->image('recibo.png', 200, 200);
    $stored = $this->aiService->storePrivatelyAndHash($file);

    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'clasificacion' => 'recibo_nomina',
                            'campos' => [
                                'nombre_trabajador' => 'CARLOS ALBERTO MENDOZA PEREZ',
                                'periodo_o_fecha' => '2026-05-15',
                                'monto_neto' => '12500.00',
                                'curp' => 'MEPC850101HTNRR01',
                            ],
                            'confianza' => 0.95,
                            'observaciones' => 'Recibo de nómina legible y consistente.',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $evaluation = $this->aiService->evaluate($stored['path'], 'nomina', $user);

    expect($evaluation->aiStatus)->toBe(LoanAiStatus::Valido)
        ->and($evaluation->classification)->toBe('recibo_nomina')
        ->and($evaluation->confidence)->toBe(0.95)
        ->and($evaluation->comparison['name_match'])->toBeTrue()
        ->and($evaluation->extractedFields['nombre_trabajador'])->toBe('CARLOS ALBERTO MENDOZA PEREZ');
});

it('evaluates evidence as dudoso when worker name does not match user profile', function () {
    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');

    $user = User::factory()->create([
        'name' => 'Ana Maria',
        'surnames' => 'Lopez Hernandez',
    ]);

    $file = UploadedFile::fake()->image('recibo_ajeno.png', 200, 200);
    $stored = $this->aiService->storePrivatelyAndHash($file);

    Http::fake([
        '*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'clasificacion' => 'recibo_nomina',
                            'campos' => [
                                'nombre_trabajador' => 'ROBERTO GOMEZ BOLAÑOS',
                                'periodo_o_fecha' => '2026-05-15',
                                'monto_neto' => '8000.00',
                            ],
                            'confianza' => 0.90,
                            'observaciones' => 'Nombre no coincide.',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $evaluation = $this->aiService->evaluate($stored['path'], 'nomina', $user);

    expect($evaluation->aiStatus)->toBe(LoanAiStatus::Dudoso)
        ->and($evaluation->comparison['name_match'])->toBeFalse();
});

it('gracefully returns fallo_tecnico on connection failure without breaking execution', function () {
    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('recibo.png', 200, 200);
    $stored = $this->aiService->storePrivatelyAndHash($file);

    Http::fake([
        '*' => Http::response([], 500),
    ]);

    $evaluation = $this->aiService->evaluate($stored['path'], 'nomina', $user);

    expect($evaluation->aiStatus)->toBe(LoanAiStatus::FalloTecnico)
        ->and($evaluation->observations)->toContain('fallo técnico');
});

it('returns fallo_tecnico instead of invalido when the AI response is empty or truncated', function () {
    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');

    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('recibo.png', 200, 200);
    $stored = $this->aiService->storePrivatelyAndHash($file);

    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => '{"clasificacion": "recibo_nom']]],
        ], 200),
    ]);

    $evaluation = $this->aiService->evaluate($stored['path'], 'nomina', $user);

    expect($evaluation->aiStatus)->toBe(LoanAiStatus::FalloTecnico);
});

it('calls the provider only once for the same file, user and expectation', function () {
    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');

    $user = User::factory()->create(['name' => 'Ana', 'surnames' => 'Lopez']);
    $stored = $this->aiService->storePrivatelyAndHash(UploadedFile::fake()->image('recibo.png', 200, 200));

    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'clasificacion' => 'recibo_nomina',
                'campos' => ['nombre_trabajador' => 'ANA LOPEZ'],
                'confianza' => 0.95,
            ])]]],
        ], 200),
    ]);

    $first = $this->aiService->evaluate($stored['path'], 'nomina', $user);
    $second = $this->aiService->evaluate($stored['path'], 'nomina', $user);

    expect($first->aiStatus)->toBe(LoanAiStatus::Valido)
        ->and($second->aiStatus)->toBe(LoanAiStatus::Valido);
    Http::assertSentCount(1);
});

it('sends a PDF without extractable text natively to the provider', function () {
    Config::set('services.document_ai.enabled', true);
    Config::set('services.document_ai.api_key', 'test-api-key');
    Config::set('services.document_ai.base_url', 'https://api.openai.com/v1');
    Config::set('services.document_ai.model', 'gpt-4o-mini');

    $user = User::factory()->create(['name' => 'Ana', 'surnames' => 'Lopez']);
    $stored = $this->aiService->storePrivatelyAndHash(UploadedFile::fake()->createWithContent('escaneado.pdf', "%PDF-1.4\n%%EOF"));

    Http::fake([
        '*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'clasificacion' => 'recibo_nomina',
                'campos' => ['nombre_trabajador' => 'ANA LOPEZ'],
                'confianza' => 0.95,
            ])]]],
        ], 200),
    ]);

    $evaluation = $this->aiService->evaluate($stored['path'], 'nomina', $user);

    expect($evaluation->aiStatus)->toBe(LoanAiStatus::Valido);
    Http::assertSent(fn ($request) => str_contains(json_encode($request->data()), 'data:application\/pdf;base64,'));
});
