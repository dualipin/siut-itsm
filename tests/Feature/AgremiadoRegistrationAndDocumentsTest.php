<?php

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Filament\Pages\Profile;
use App\Filament\Resources\Users\Pages\ReviewDocuments;
use App\Models\User;
use App\Services\DocumentAIService;
use App\Services\RegistrationStaging;
use App\Support\DocumentAIResult;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function createFakePdf(string $name = 'document.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
    );
}

function fakeIneJpg(string $name = 'ine.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 800, 500);
}

function fakeNominaJpg(string $name = 'nomina.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 800, 600);
}

/** @param array<string, mixed> $overrides */
function makeAiResult(array $overrides = []): DocumentAIResult
{
    return new DocumentAIResult(
        isValidDocument: $overrides['valid'] ?? true,
        documentType: $overrides['type'] ?? 'ine',
        extractedFields: $overrides['fields'] ?? ['nombre' => 'CARLOS', 'apellidos' => 'MENDOZA LOPEZ', 'curp' => 'MELC950515HDFRRN09', 'fecha_nacimiento' => '1995-05-15', 'domicilio' => '', 'vigencia' => '2030'],
        confidenceScore: $overrides['confidence'] ?? 0.93,
        observations: $overrides['observations'] ?? '',
        aiFlags: $overrides['flags'] ?? [],
    );
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('public');
    Storage::fake('local');
});

// ── Basic access ──────────────────────────────────────────────────────────────

test('guest can access portal register page and is redirected 301 to public register', function () {
    $this->get('/portal/register')
        ->assertRedirect('/registro')
        ->assertStatus(301);
});

// ── Happy path registration with AI autofill ──────────────────────────────────

test('guest can register as agremiado with documents and profile data', function () {
    config()->set('services.document_ai.enabled', false);

    $staging = app(RegistrationStaging::class);

    $inePath = 'temp-uploads/registro/test-ine.jpg';
    $nominaPath = 'temp-uploads/registro/test-nomina.jpg';
    $photoPath = 'temp-uploads/registro/test-photo.jpg';

    Storage::disk('local')->put($inePath, (string) fakeIneJpg()->getContent());
    Storage::disk('local')->put($nominaPath, (string) fakeNominaJpg()->getContent());
    Storage::disk('local')->put($photoPath, (string) UploadedFile::fake()->image('carlos.jpg')->getContent());

    $ineToken = $staging->stage('ine', $inePath, [
        'is_valid_document' => true,
        'document_type' => 'ine',
        'extracted_fields' => [
            'nombre' => 'Carlos',
            'apellidos' => 'Mendoza Lopez',
            'curp' => 'MELC950515HDFRRN09',
            'vigencia' => '2030',
        ],
    ]);

    $nominaToken = $staging->stage('nomina', $nominaPath, [
        'is_valid_document' => true,
        'document_type' => 'comprobante_pago',
        'extracted_fields' => [
            'fecha_pago' => now()->toDateString(),
        ],
    ]);

    $photoToken = $staging->stage('photo', $photoPath, null);

    $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza Lopez',
        'email' => 'carlos.mendoza@siut.org',
        'phone' => '9931234567',
        'birth_date' => '1995-05-15',
        'curp' => 'MELC950515HDFRRN09',
        'nss' => '12345678901',
        'address' => 'Calle Reforma 123, Villahermosa',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
    ])->assertOk();

    $user = User::where('email', 'carlos.mendoza@siut.org')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Carlos')
        ->and($user->surnames)->toBe('Mendoza Lopez')
        ->and($user->phone)->toBe('9931234567')
        ->and($user->curp)->toBe('MELC950515HDFRRN09')
        ->and($user->nss)->toBe('12345678901')
        ->and($user->role)->toBe(UserRole::Agremiado)
        ->and($user->is_active)->toBeTrue()
        ->and($user->photo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($user->photo_path);

    // Welcome notification
    expect($user->notifications()->count())->toBeGreaterThanOrEqual(1);
    expect($user->notifications()->first()->data['title'])->toContain('Bienvenido');
});

// ── INE and nómina required ───────────────────────────────────────────────────

test('registration requires ine_token, nomina_token, and photo_token', function () {
    $this->postJson('/api/registro', [
        'name' => 'Sin',
        'surnames' => 'Documentos',
        'email' => 'sin.docs@siut.org',
        'birth_date' => '1995-05-15',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['ine_token', 'nomina_token', 'photo_token']);
});

// ── CURP validation ───────────────────────────────────────────────────────────

test('registration blocks on invalid CURP format', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/d1.jpg', 'content');
    $token = $staging->stage('ine', 'temp/d1.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Test',
        'surnames' => 'User',
        'email' => 'test.curp@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'INVALID',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $token,
        'nomina_token' => $token,
        'photo_token' => $token,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['curp']);
});

test('registration blocks when birth date does not match CURP', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/d1.jpg', 'content');
    Storage::disk('local')->put('temp/d2.jpg', 'content');
    Storage::disk('local')->put('temp/d3.jpg', 'content');

    $t1 = $staging->stage('ine', 'temp/d1.jpg');
    $t2 = $staging->stage('nomina', 'temp/d2.jpg');
    $t3 = $staging->stage('photo', 'temp/d3.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Test',
        'surnames' => 'User',
        'email' => 'test.dob@siut.org',
        'birth_date' => '1990-01-01', // Does not match CURP (positions 4-9 = 950515)
        'curp' => 'MELC950515HDFRRN09',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $t1,
        'nomina_token' => $t2,
        'photo_token' => $t3,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['birth_date']);
});

// ── AI-driven blocking checks ─────────────────────────────────────────────────

test('registration blocks when AI detects payslip older than 31 days', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/ine.jpg', 'content');
    Storage::disk('local')->put('temp/nom.jpg', 'content');
    Storage::disk('local')->put('temp/pho.jpg', 'content');

    $ineToken = $staging->stage('ine', 'temp/ine.jpg', [
        'is_valid_document' => true,
        'extracted_fields' => ['vigencia' => '2030'],
    ]);

    $nominaToken = $staging->stage('nomina', 'temp/nom.jpg', [
        'is_valid_document' => true,
        'extracted_fields' => [
            'nombre' => 'Carlos',
            'fecha_pago' => now()->subDays(45)->toDateString(),
        ],
    ]);

    $photoToken = $staging->stage('photo', 'temp/pho.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza Lopez',
        'email' => 'carlos.old@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'MELC950515HDFRRN09',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['nomina_token']);
});

test('registration blocks when AI detects expired INE', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/ine.jpg', 'content');
    Storage::disk('local')->put('temp/nom.jpg', 'content');
    Storage::disk('local')->put('temp/pho.jpg', 'content');

    $ineToken = $staging->stage('ine', 'temp/ine.jpg', [
        'is_valid_document' => true,
        'extracted_fields' => [
            'nombre' => 'Carlos',
            'vigencia' => (string) (now()->year - 2),
        ],
    ]);

    $nominaToken = $staging->stage('nomina', 'temp/nom.jpg');
    $photoToken = $staging->stage('photo', 'temp/pho.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza',
        'email' => 'carlos.expired@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'MELC950515HDFRRN09',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['ine_token']);
});

// ── AI down / timeout: registration continues in manual mode ──────────────────

test('registration continues in manual mode when AI is down', function () {
    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/ine.jpg', 'content');
    Storage::disk('local')->put('temp/nom.jpg', 'content');
    Storage::disk('local')->put('temp/pho.jpg', 'content');

    // Documents staged without AI extraction result (null ai)
    $ineToken = $staging->stage('ine', 'temp/ine.jpg', null, ['No fue posible verificar el documento automáticamente.']);
    $nominaToken = $staging->stage('nomina', 'temp/nom.jpg', null);
    $photoToken = $staging->stage('photo', 'temp/pho.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Carlos',
        'surnames' => 'Mendoza Lopez',
        'email' => 'carlos.noai@siut.org',
        'birth_date' => '1995-05-15',
        'curp' => 'MELC950515HDFRRN09',
        'nss' => '98765432101',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $ineToken,
        'nomina_token' => $nominaToken,
        'photo_token' => $photoToken,
    ])->assertOk();

    expect(User::where('email', 'carlos.noai@siut.org')->exists())->toBeTrue();
});

// ── Rate limiting ─────────────────────────────────────────────────────────────

test('AI throttle per hour returns null result with warning', function () {
    config()->set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1/',
        'api_key' => 'key',
        'model' => 'test-model',
        'timeout' => 5,
        'sync_timeout' => 5,
        'max_tokens' => 512,
        'max_dimension' => 200,
        'jpeg_quality' => 70,
        'store_max_dimension' => 400,
        'store_jpeg_quality' => 80,
        'json_mode' => true,
        'min_reject_confidence' => 0.7,
        'throttle_per_hour' => 2,
        'daily_cap' => 300,
        'headers' => [],
    ]);

    Http::fake();

    // Exhaust the hourly limit
    $key = 'docai:hour:ip:127.0.0.1';
    RateLimiter::hit($key, 3600);
    RateLimiter::hit($key, 3600);

    $service = app(DocumentAIService::class);
    $bytes = (function () {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    })();

    $result = $service->review($bytes, 'image/png', UserDocumentType::Ine, 'ip:127.0.0.1');

    expect($result['result'])->toBeNull()
        ->and($result['warnings'])->not->toBeEmpty();

    Http::assertNothingSent();
});

test('AI daily cap returns null result with warning', function () {
    config()->set('services.document_ai', [
        'enabled' => true,
        'base_url' => 'https://ai.test/v1/',
        'api_key' => 'key',
        'model' => 'test-model',
        'timeout' => 5,
        'sync_timeout' => 5,
        'max_tokens' => 512,
        'max_dimension' => 200,
        'jpeg_quality' => 70,
        'store_max_dimension' => 400,
        'store_jpeg_quality' => 80,
        'json_mode' => true,
        'min_reject_confidence' => 0.7,
        'throttle_per_hour' => 100,
        'daily_cap' => 0,
        'headers' => [],
    ]);

    Http::fake();

    $service = app(DocumentAIService::class);
    $bytes = (function () {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($img);

        return ob_get_clean();
    })();

    $result = $service->review($bytes, 'image/png', UserDocumentType::Ine, 'ip:127.0.0.1');

    expect($result['result'])->toBeNull()
        ->and($result['warnings'])->not->toBeEmpty();

    Http::assertNothingSent();
});

// ── Role enforcement ──────────────────────────────────────────────────────────

test('registration strictly enforces agremiado role', function () {
    config()->set('services.document_ai.enabled', false);

    $staging = app(RegistrationStaging::class);
    Storage::disk('local')->put('temp/i.jpg', 'content');
    Storage::disk('local')->put('temp/n.jpg', 'content');
    Storage::disk('local')->put('temp/p.jpg', 'content');

    $t1 = $staging->stage('ine', 'temp/i.jpg');
    $t2 = $staging->stage('nomina', 'temp/n.jpg');
    $t3 = $staging->stage('photo', 'temp/p.jpg');

    $this->postJson('/api/registro', [
        'name' => 'Admin Attempt',
        'surnames' => 'Hacker',
        'email' => 'hacker@siut.org',
        'birth_date' => '1990-01-01',
        'curp' => 'HACK900101HDFRRN01',
        'password' => 'SecurePassword123!',
        'password_confirmation' => 'SecurePassword123!',
        'ine_token' => $t1,
        'nomina_token' => $t2,
        'photo_token' => $t3,
        'role' => 'admin',
    ])->assertOk();

    $user = User::where('email', 'hacker@siut.org')->first();

    expect($user->role)->toBe(UserRole::Agremiado);
});

// ── Profile document upload ───────────────────────────────────────────────────

test('agremiado can upload affiliation documents via profile and replace them', function () {
    $user = User::factory()->create([
        'role' => UserRole::Agremiado,
    ]);

    $this->actingAs($user);

    $pdfFile = createFakePdf('afiliacion.pdf');

    Livewire::test(Profile::class)
        ->fillForm([
            'afiliacion' => $pdfFile,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->getFirstMedia('afiliacion'))->not->toBeNull();
    expect($user->getDocumentStatus(UserDocumentType::Afiliacion))->toBe(DocumentStatus::Pending);
});

test('profile: no ProcessDocumentAIJob is dispatched on save', function () {
    Queue::fake();

    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $this->actingAs($user);

    Livewire::test(Profile::class)
        ->fillForm(['afiliacion' => createFakePdf('afi.pdf')])
        ->call('save');

    Queue::assertNothingPushed();
});

test('profile: AI metadata is persisted on save without a second AI call', function () {
    config()->set('services.document_ai.enabled', false);

    $user = User::factory()->create(['role' => UserRole::Agremiado]);
    $this->actingAs($user);

    $aiPreview = [
        'is_valid_document' => true,
        'document_type' => 'ine',
        'extracted_fields' => ['nombre' => 'JUAN', 'curp' => 'PELJ800101HDFRPN09'],
        'confidence_score' => 0.95,
        'observations' => '',
        'ai_flags' => [],
    ];

    // Stage the AI preview via set() so it survives the Livewire request boundary
    Livewire::test(Profile::class)
        ->set('aiPreviews', [UserDocumentType::Ine->value => $aiPreview])
        ->fillForm(['ine' => fakeIneJpg()])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    $media = $user->getFirstMedia(UserDocumentType::Ine->value);

    expect($media)->not->toBeNull();
    expect($media->getCustomProperty('status'))->toBe(DocumentStatus::Pending->value);
    expect($media->getCustomProperty('ai_processed_at'))->not->toBeNull();
    expect($media->getCustomProperty('extracted_data.extracted_fields.nombre'))->toBe('JUAN');
});

// ── Access control ────────────────────────────────────────────────────────────

test('leader and admin can access UserResource but agremiado is forbidden', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $lider = User::factory()->create(['role' => UserRole::Lider]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($agremiado)
        ->get('/portal/users')
        ->assertForbidden();

    $this->actingAs($lider)
        ->get('/portal/users')
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get('/portal/users')
        ->assertSuccessful();
});

// ── Document approval workflow ────────────────────────────────────────────────

test('leader or admin can approve user documents and user overall status updates', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);

    // Attach all 5 documents
    foreach (UserDocumentType::cases() as $type) {
        $agremiado->addMedia(createFakePdf("{$type->value}.pdf"))
            ->toMediaCollection($type->value);
    }

    expect($agremiado->areAllDocumentsValid())->toBeFalse();
    expect($agremiado->getOverallDocumentStatus())->toBe(DocumentStatus::Pending);

    $this->actingAs($admin);

    Livewire::test(ReviewDocuments::class, ['record' => $agremiado->getRouteKey()])
        ->callAction('approveAllDocuments')
        ->assertHasNoActionErrors();

    $agremiado->refresh();

    expect($agremiado->areAllDocumentsValid())->toBeTrue();
    expect($agremiado->getOverallDocumentStatus())->toBe(DocumentStatus::Valid);

    // Agremiado should have received a notification
    $latestNotification = $agremiado->notifications()->latest()->first();
    expect($latestNotification)->not->toBeNull();
    expect($latestNotification->data['title'])->toContain('validados');
});

test('leader or admin can mark document as invalid with reason and notify user', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);

    $agremiado->addMedia(createFakePdf('domicilio.pdf'))
        ->toMediaCollection(UserDocumentType::ComprobanteDomicilio->value);

    $this->actingAs($admin);

    Livewire::test(ReviewDocuments::class, ['record' => $agremiado->getRouteKey()])
        ->callAction('rejectDocument', data: [
            'rejection_reason' => 'El comprobante tiene más de 3 meses de antigüedad.',
        ], arguments: ['type' => UserDocumentType::ComprobanteDomicilio->value])
        ->assertHasNoActionErrors();

    $agremiado->refresh();

    expect($agremiado->getDocumentStatus(UserDocumentType::ComprobanteDomicilio))->toBe(DocumentStatus::Invalid);
    expect($agremiado->getDocumentRejectionReason(UserDocumentType::ComprobanteDomicilio))->toBe('El comprobante tiene más de 3 meses de antigüedad.');
    expect($agremiado->getOverallDocumentStatus())->toBe(DocumentStatus::Invalid);

    // User notified with reason
    $notification = $agremiado->notifications()->latest()->first();
    expect($notification->data['body'])->toContain('más de 3 meses de antigüedad');
});

test('user can replace invalid document and status resets to pending', function () {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
        'phone' => '9931234567',
    ]);

    $media = $agremiado->addMedia(createFakePdf('ine.pdf'))
        ->toMediaCollection('ine');

    $media->setCustomProperty('status', DocumentStatus::Invalid->value);
    $media->setCustomProperty('rejection_reason', 'Foto borrosa');
    $media->save();

    expect($agremiado->getDocumentStatus(UserDocumentType::Ine))->toBe(DocumentStatus::Invalid);

    // Agremiado replaces document
    $this->actingAs($agremiado);

    $newPdf = createFakePdf('ine_clarita.pdf');

    Livewire::test(Profile::class)
        ->fillForm([
            'ine' => $newPdf,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $agremiado->refresh();

    expect($agremiado->getDocumentStatus(UserDocumentType::Ine))->toBe(DocumentStatus::Pending);
    expect($agremiado->getDocumentRejectionReason(UserDocumentType::Ine))->toBeNull();
});

test('leader or admin can approve all uploaded documents with single action', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);

    foreach (UserDocumentType::cases() as $type) {
        $agremiado->addMedia(createFakePdf("{$type->value}.pdf"))
            ->toMediaCollection($type->value);
    }

    $this->actingAs($admin);

    Livewire::test(ReviewDocuments::class, ['record' => $agremiado->getRouteKey()])
        ->callAction('approveAllDocuments')
        ->assertHasNoActionErrors();

    $agremiado->refresh();

    expect($agremiado->areAllDocumentsValid())->toBeTrue();
    expect($agremiado->getOverallDocumentStatus())->toBe(DocumentStatus::Valid);

    $latestNotification = $agremiado->notifications()->latest()->first();
    expect($latestNotification)->not->toBeNull()
        ->and($latestNotification->data['title'])->toContain('validados');
});

test('valid document field is disabled for agremiado in profile', function () {
    $agremiado = User::factory()->create([
        'role' => UserRole::Agremiado,
        'phone' => '9931234567',
    ]);

    $media = $agremiado->addMedia(createFakePdf('afiliacion.pdf'))
        ->toMediaCollection('afiliacion');
    $media->setCustomProperty('status', DocumentStatus::Valid->value);
    $media->save();

    $this->actingAs($agremiado);

    $component = Livewire::test(Profile::class);
    $field = $component->instance()->getSchema('form')->getComponent('afiliacion');

    expect($field->isDisabled())->toBeTrue();
});

// ── Document serving ──────────────────────────────────────────────────────────

test('admin can view agremiado document via portal.users.documents.show', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);

    $agremiado->addMedia(createFakePdf('ine.pdf'))
        ->toMediaCollection('ine');

    $response = $this->actingAs($admin)
        ->get(route('portal.users.documents.show', ['user' => $agremiado, 'type' => 'ine']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('leader can view agremiado document via portal.users.documents.show', function () {
    $lider = User::factory()->create(['role' => UserRole::Lider]);
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);

    $agremiado->addMedia(createFakePdf('curp.pdf'))
        ->toMediaCollection('curp');

    $response = $this->actingAs($lider)
        ->get(route('portal.users.documents.show', ['user' => $agremiado, 'type' => 'curp']));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
});

test('agremiado can view their own document but not another agremiado document', function () {
    $agremiado1 = User::factory()->create(['role' => UserRole::Agremiado]);
    $agremiado2 = User::factory()->create(['role' => UserRole::Agremiado]);

    $agremiado1->addMedia(createFakePdf('afiliacion.pdf'))
        ->toMediaCollection('afiliacion');

    // Own document -> 200 OK
    $response = $this->actingAs($agremiado1)
        ->get(route('portal.users.documents.show', ['user' => $agremiado1, 'type' => 'afiliacion']));
    $response->assertOk();

    // Another user's document -> 403 Forbidden
    $forbiddenResponse = $this->actingAs($agremiado2)
        ->get(route('portal.users.documents.show', ['user' => $agremiado1, 'type' => 'afiliacion']));
    $forbiddenResponse->assertForbidden();
});

test('guest cannot access document route and is redirected to login', function () {
    $agremiado = User::factory()->create(['role' => UserRole::Agremiado]);

    $agremiado->addMedia(createFakePdf('afiliacion.pdf'))
        ->toMediaCollection('afiliacion');

    $response = $this->get(route('portal.users.documents.show', ['user' => $agremiado, 'type' => 'afiliacion']));
    $response->assertRedirect('/portal/login');
});

// ── Media collection mime types ───────────────────────────────────────────────

test('ine collection accepts image types but curp collection only accepts pdf', function () {
    $user = User::factory()->create(['role' => UserRole::Agremiado]);

    $ineCollection = $user->getMediaCollection(UserDocumentType::Ine->value);
    $curpCollection = $user->getMediaCollection(UserDocumentType::Curp->value);

    $ineMimes = $ineCollection->acceptsMimeTypes;
    $curpMimes = $curpCollection->acceptsMimeTypes;

    expect($ineMimes)->toContain('image/jpeg')
        ->and($ineMimes)->toContain('image/png')
        ->and($ineMimes)->toContain('image/webp')
        ->and($curpMimes)->not->toContain('image/jpeg');
});
