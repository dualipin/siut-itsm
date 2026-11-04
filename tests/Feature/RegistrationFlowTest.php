<?php

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\RegistrationStaging;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    RateLimiter::clear('docai:hour:ip:127.0.0.1');
});

function fakeRegistrationPdf(string $name = 'test.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
    );
}

function fakeRegistrationIne(): UploadedFile
{
    return UploadedFile::fake()->image('ine.jpg', 800, 500);
}

function fakeRegistrationNomina(): UploadedFile
{
    return UploadedFile::fake()->image('nomina.jpg', 800, 600);
}

function fakeRegistrationPhoto(): UploadedFile
{
    return UploadedFile::fake()->image('avatar.jpg', 400, 400);
}

test('guest can access registro page and see island container', function () {
    $response = $this->get('/registro');

    $response->assertOk();
    $response->assertSee('data-vue="registro/register-form"', false);
});

test('authenticated user accessing registro is redirected to portal', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    $response = $this->actingAs($user)->get('/registro');

    $response->assertRedirect('/portal');
});

test('legacy portal register route redirects 301 to registro', function () {
    $response = $this->get('/portal/register');

    $response->assertRedirect('/registro');
    $response->assertStatus(301);
});

test('uploading document validates kind, size, and file type', function () {
    // Missing kind and file
    $this->postJson('/api/registro/documentos', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['kind', 'file']);

    // Invalid kind
    $this->postJson('/api/registro/documentos', [
        'kind' => 'invalid_kind',
        'file' => fakeRegistrationIne(),
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['kind']);

    // Invalid file extension
    $this->postJson('/api/registro/documentos', [
        'kind' => 'ine',
        'file' => UploadedFile::fake()->create('test.exe', 100),
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

test('uploading photo does not call AI and returns not_applicable status', function () {
    Http::fake();

    $response = $this->postJson('/api/registro/documentos', [
        'kind' => 'photo',
        'file' => fakeRegistrationPhoto(),
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'fields', 'warnings', 'ai_status'])
        ->assertJson([
            'ai_status' => 'not_applicable',
            'warnings' => [],
        ]);

    Http::assertNothingSent();

    $token = $response->json('token');
    $staging = app(RegistrationStaging::class);
    $entry = $staging->resolve($token, 'photo');

    expect($entry)->not->toBeNull();
});

test('uploading INE with AI enabled extracts fields and stages result', function () {
    config()->set('services.document_ai.enabled', true);
    config()->set('services.document_ai.base_url', 'https://ai.test/v1');
    config()->set('services.document_ai.api_key', 'test-key');
    config()->set('services.document_ai.model', 'test-model');

    Http::fake([
        'https://ai.test/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'is_valid_document' => true,
                            'document_type' => 'ine',
                            'extracted_fields' => [
                                'nombre' => 'JUAN CARLOS',
                                'apellidos' => 'HERNANDEZ PEREZ',
                                'curp' => 'HEPJ900101HDFRRN01',
                                'fecha_nacimiento' => '1990-01-01',
                                'domicilio' => 'Calle 5 #123',
                                'vigencia' => '2030',
                            ],
                            'confidence_score' => 0.95,
                            'observations' => 'Credencial INE vigente y legible',
                        ]),
                    ],
                ],
            ],
        ], 200),
    ]);

    $response = $this->postJson('/api/registro/documentos', [
        'kind' => 'ine',
        'file' => fakeRegistrationIne(),
    ]);

    $response->assertOk()
        ->assertJson([
            'ai_status' => 'success',
            'fields' => [
                'nombre' => 'JUAN CARLOS',
                'apellidos' => 'HERNANDEZ PEREZ',
                'curp' => 'HEPJ900101HDFRRN01',
                'fecha_nacimiento' => '1990-01-01',
            ],
        ]);

    $token = $response->json('token');
    $staging = app(RegistrationStaging::class);
    $entry = $staging->resolve($token, 'ine');

    expect($entry)->not->toBeNull()
        ->and($entry['ai']['extracted_fields']['nombre'])->toBe('JUAN CARLOS');
});

test('uploading document when AI is down or disabled degrades gracefully', function () {
    config()->set('services.document_ai.enabled', false);

    $response = $this->postJson('/api/registro/documentos', [
        'kind' => 'nomina',
        'file' => fakeRegistrationNomina(),
    ]);

    $response->assertOk()
        ->assertJson([
            'ai_status' => 'failed',
        ]);

    expect($response->json('token'))->not->toBeNull();
});

test('successful registration creates Agremiado user, attaches media with session AI data, and logs user in', function () {
    $staging = app(RegistrationStaging::class);

    // Prepare staged files on local disk
    $inePath = 'temp-uploads/registro/fake-ine.jpg';
    $nominaPath = 'temp-uploads/registro/fake-nomina.jpg';
    $photoPath = 'temp-uploads/registro/fake-photo.jpg';

    Storage::disk('local')->put($inePath, (string) fakeRegistrationIne()->getContent());
    Storage::disk('local')->put($nominaPath, (string) fakeRegistrationNomina()->getContent());
    Storage::disk('local')->put($photoPath, (string) fakeRegistrationPhoto()->getContent());

    $ineToken = $staging->stage('ine', $inePath, [
        'is_valid_document' => true,
        'document_type' => 'ine',
        'extracted_fields' => [
            'nombre' => 'CARLOS',
            'apellidos' => 'MENDOZA LOPEZ',
            'curp' => 'MELC950515HDFRRN09',
            'vigencia' => '2030',
        ],
    ]);

    $nominaToken = $staging->stage('nomina', $nominaPath, [
        'is_valid_document' => true,
        'document_type' => 'comprobante_pago',
        'extracted_fields' => [
            'nombre' => 'CARLOS MENDOZA',
            'fecha_pago' => now()->subDays(5)->toDateString(),
            'salario' => '12000.00',
        ],
    ]);

    $photoToken = $staging->stage('photo', $photoPath, null);

    $payload = [
        'name' => 'Carlos',
        'surnames' => 'Mendoza Lopez',
        'email' => 'carlos.mendoza@siut.org',
        'phone' => '9931234567',
        'birth_date' => '1995-05-15',
        'curp' => 'MELC950515HDFRRN09',
        'nss' => '12345678901',
        'category' => 'Profesor de Asignatura',
        'salary' => 12000,
        'address' => 'Av. Universidad 456',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
        'role' => 'admin', // Malicious attempt to escalate role
    ];

    $response = $this->postJson('/api/registro', $payload);

    $response->assertOk()
        ->assertJsonStructure(['redirect', 'message']);

    $user = User::where('email', 'carlos.mendoza@siut.org')->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Agremiado) // Role must strictly be Agremiado
        ->and($user->is_active)->toBeTrue()
        ->and($user->photo_path)->not->toBeNull();

    // Check photo moved to public disk
    Storage::disk('public')->assertExists($user->photo_path);

    // Temporary files deleted
    Storage::disk('local')->assertMissing($inePath);
    Storage::disk('local')->assertMissing($nominaPath);
    Storage::disk('local')->assertMissing($photoPath);

    // User authenticated
    expect(Auth::id())->toBe($user->id);

    // Welcome notification created
    expect($user->notifications()->count())->toBeGreaterThanOrEqual(1);

    // Spatie media documents attached
    $ineMedia = $user->getDocumentMedia(UserDocumentType::Ine);
    $nominaMedia = $user->getDocumentMedia(UserDocumentType::ComprobantePago);

    expect($ineMedia)->not->toBeNull()
        ->and($ineMedia->getCustomProperty('status'))->toBe(DocumentStatus::Pending->value)
        ->and($ineMedia->getCustomProperty('extracted_data.extracted_fields.nombre'))->toBe('CARLOS')
        ->and($nominaMedia)->not->toBeNull();
});

test('registration blocks when CURP format is invalid', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/dummy.jpg', 'fake');
    $token = $staging->stage('ine', 'temp/dummy.jpg');

    $response = $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza',
        'email' => 'carlos@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'INVALID_CURP',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'ine_token' => $token,
        'nomina_token' => $token,
        'photo_token' => $token,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['curp']);
});

test('registration blocks when birth date does not match CURP', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/dummy.jpg', 'fake');
    $ineToken = $staging->stage('ine', 'temp/dummy.jpg');
    $nominaToken = $staging->stage('nomina', 'temp/dummy.jpg');
    $photoToken = $staging->stage('photo', 'temp/dummy.jpg');

    $response = $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza',
        'email' => 'carlos@siut.org',
        'birth_date' => '1990-01-01', // Does not match 950515 in CURP
        'curp' => 'MELC950515HDFRRN09',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['birth_date']);
});

test('registration blocks when AI detects expired INE in session', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/ine.jpg', 'fake');
    Storage::disk('local')->put('temp/nom.jpg', 'fake');
    Storage::disk('local')->put('temp/pho.jpg', 'fake');

    $ineToken = $staging->stage('ine', 'temp/ine.jpg', [
        'is_valid_document' => true,
        'extracted_fields' => [
            'vigencia' => (string) (now()->year - 2),
        ],
    ]);
    $nominaToken = $staging->stage('nomina', 'temp/nom.jpg');
    $photoToken = $staging->stage('photo', 'temp/pho.jpg');

    $response = $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza',
        'email' => 'carlos@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'MELC950515HDFRRN09',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['ine_token']);
});

test('registration blocks when AI detects payslip older than 31 days in session', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/ine.jpg', 'fake');
    Storage::disk('local')->put('temp/nom.jpg', 'fake');
    Storage::disk('local')->put('temp/pho.jpg', 'fake');

    $ineToken = $staging->stage('ine', 'temp/ine.jpg', [
        'is_valid_document' => true,
        'extracted_fields' => ['vigencia' => '2030'],
    ]);
    $nominaToken = $staging->stage('nomina', 'temp/nom.jpg', [
        'is_valid_document' => true,
        'extracted_fields' => ['fecha_pago' => now()->subDays(45)->toDateString()],
    ]);
    $photoToken = $staging->stage('photo', 'temp/pho.jpg');

    $response = $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza',
        'email' => 'carlos@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'MELC950515HDFRRN09',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nomina_token']);
});

test('registration blocks when applicant is under 18 years old', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/dummy.jpg', 'fake');
    $ineToken = $staging->stage('ine', 'temp/dummy.jpg');
    $nominaToken = $staging->stage('nomina', 'temp/dummy.jpg');
    $photoToken = $staging->stage('photo', 'temp/dummy.jpg');

    $dob = now()->subYears(17)->format('Y-m-d');
    $dobCurp = now()->subYears(17)->format('ymd');

    $response = $this->postJson('/api/registro', [
        'name' => 'Menor',
        'surnames' => 'De Edad',
        'email' => 'menor@siut.org',
        'birth_date' => $dob,
        'curp' => 'MENO'.$dobCurp.'HDFRRN01',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['birth_date']);
});

test('registration blocks when CURP, email, or NSS are already registered', function () {
    User::factory()->create([
        'email' => 'existing@siut.org',
        'curp' => 'DUPL900101HDFRRN01',
        'nss' => '99887766554',
    ]);

    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/d1.jpg', 'fake');
    $ineToken = $staging->stage('ine', 'temp/d1.jpg');
    $nominaToken = $staging->stage('nomina', 'temp/d1.jpg');
    $photoToken = $staging->stage('photo', 'temp/d1.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Duplicado',
        'surnames' => 'Prueba',
        'email' => 'existing@siut.org',
        'birth_date' => '1990-01-01',
        'curp' => 'DUPL900101HDFRRN01',
        'nss' => '99887766554',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'curp', 'nss']);
});

test('route throttle returns 429 when exceeding limits', function () {
    RateLimiter::clear('registro:5,1');
    // The route has throttle:5,1
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/registro', []);
    }

    $response = $this->postJson('/api/registro', []);
    $response->assertStatus(429);
});

test('purge command deletes files older than 3 hours and keeps fresh ones', function () {
    $oldFile = 'temp-uploads/registro/old-file.pdf';
    $freshFile = 'temp-uploads/registro/fresh-file.pdf';

    Storage::disk('local')->put($oldFile, 'old content');
    Storage::disk('local')->put($freshFile, 'fresh content');

    // Manipulate modification time for old file
    touch(Storage::disk('local')->path($oldFile), now()->subHours(4)->timestamp);
    touch(Storage::disk('local')->path($freshFile), now()->subMinutes(10)->timestamp);

    Artisan::call('registration:purge-temp');

    Storage::disk('local')->assertMissing($oldFile);
    Storage::disk('local')->assertExists($freshFile);
});
