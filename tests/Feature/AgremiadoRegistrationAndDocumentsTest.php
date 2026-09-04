<?php

use App\Enums\DocumentStatus;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Profile;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function createFakePdf(string $name = 'document.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
    );
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('public');
});

test('guest can access portal register page without logic exception', function () {
    $this->get('/portal/register')
        ->assertSuccessful()
        ->assertSee('Registro de Agremiado');
});

test('guest can register as agremiado with profile details and required photo', function () {
    $photo = UploadedFile::fake()->image('carlos.jpg');

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Carlos',
            'surnames' => 'Mendoza Lopez',
            'email' => 'carlos.mendoza@siut.org',
            'phone' => '9931234567',
            'birth_date' => '1995-05-15',
            'curp' => 'MELC950515HDFRRN09',
            'nss' => '12345678901',
            'address' => 'Calle Reforma 123, Villahermosa',
            'photo_path' => $photo,
            'password' => 'SecurePassword123!',
            'passwordConfirmation' => 'SecurePassword123!',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

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

    // Verify welcome notification
    expect($user->notifications()->count())->toBeGreaterThanOrEqual(1);
    expect($user->notifications()->first()->data['title'])->toContain('Bienvenido');
});

test('profile photo is required during registration', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Sin',
            'surnames' => 'Foto',
            'email' => 'sin.foto@siut.org',
            'phone' => '9931234567',
            'birth_date' => '1995-05-15',
            'photo_path' => null,
            'password' => 'SecurePassword123!',
            'passwordConfirmation' => 'SecurePassword123!',
        ])
        ->call('register')
        ->assertHasFormErrors(['photo_path' => ['required']]);
});

test('registration strictly enforces agremiado role', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Admin Attempt',
            'surnames' => 'Hacker',
            'email' => 'hacker@siut.org',
            'birth_date' => '1990-01-01',
            'photo_path' => UploadedFile::fake()->image('hacker.jpg'),
            'password' => 'SecurePassword123!',
            'passwordConfirmation' => 'SecurePassword123!',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'hacker@siut.org')->first();

    expect($user->role)->toBe(UserRole::Agremiado);
});

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

    // Validate using the action on UsersTable
    Livewire::test(ListUsers::class)
        ->callTableAction('validateDocuments', $agremiado, [
            'status_afiliacion' => DocumentStatus::Valid->value,
            'status_comprobante_domicilio' => DocumentStatus::Valid->value,
            'status_ine' => DocumentStatus::Valid->value,
            'status_comprobante_pago' => DocumentStatus::Valid->value,
            'status_curp' => DocumentStatus::Valid->value,
        ])
        ->assertHasNoTableActionErrors();

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
        ->toMediaCollection('comprobante_domicilio');

    $this->actingAs($admin);

    Livewire::test(ListUsers::class)
        ->callTableAction('validateDocuments', $agremiado, [
            'status_comprobante_domicilio' => DocumentStatus::Invalid->value,
            'rejection_comprobante_domicilio' => 'El comprobante tiene más de 3 meses de antigüedad.',
        ])
        ->assertHasNoTableActionErrors();

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

    Livewire::test(ListUsers::class)
        ->callTableAction('approveAllDocuments', $agremiado)
        ->assertHasNoTableActionErrors();

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
