<?php

use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Inquiries\Pages\EditInquiry;
use App\Filament\Resources\Inquiries\Pages\ListInquiries;
use App\Filament\Resources\Inquiries\RelationManagers\AnswersRelationManager;
use App\Models\Inquiry;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('guest can submit public inquiry', function () {
    $response = $this->post('/dudas', [
        'title' => '¿Cómo afiliarme al sindicato?',
        'body' => 'Soy de nuevo ingreso en el ITSM y quisiera conocer los requisitos.',
        'category' => 'Afiliación',
        'is_public' => true,
        'guest_name' => 'María Visitante',
        'guest_email' => 'maria@visitante.com',
    ]);

    $response->assertSessionHas('success');

    $this->assertDatabaseHas('inquiries', [
        'title' => '¿Cómo afiliarme al sindicato?',
        'guest_name' => 'María Visitante',
        'guest_email' => 'maria@visitante.com',
        'is_public' => true,
    ]);
});

test('logged in agremiado can submit private inquiry', function () {
    $agremiado = User::factory()->create([
        'name' => 'Pedro',
        'role' => UserRole::Agremiado,
    ]);

    $response = $this->actingAs($agremiado)->post('/dudas', [
        'title' => 'Duda sobre deducciones en nómina',
        'body' => 'Tengo una discrepancia en mis deducciones de este mes.',
        'category' => 'Cuotas y Finanzas',
        'is_public' => false,
    ]);

    $response->assertSessionHas('success');

    $this->assertDatabaseHas('inquiries', [
        'user_id' => $agremiado->id,
        'title' => 'Duda sobre deducciones en nómina',
        'is_public' => false,
    ]);
});

test('public inquiries page displays only public inquiries', function () {
    $publicInquiry = Inquiry::create([
        'title' => 'Duda pública de prueba',
        'body' => 'Esta es visible para todos.',
        'category' => 'Trámites',
        'is_public' => true,
        'guest_name' => 'Anónimo',
    ]);

    $privateInquiry = Inquiry::create([
        'title' => 'Duda secreta privada',
        'body' => 'Esta no debe salir en el listado público.',
        'category' => 'General',
        'is_public' => false,
        'guest_name' => 'Privado',
    ]);

    $response = $this->get('/dudas');
    $response->assertOk();

    // Verify public detail page works
    $detailResponse = $this->get("/dudas/{$publicInquiry->slug}");
    $detailResponse->assertOk();

    // Verify private inquiry cannot be accessed by unauthenticated guest
    $privateDetailResponse = $this->get("/dudas/{$privateInquiry->slug}");
    $privateDetailResponse->assertForbidden();
});

test('admin can manage inquiries and add answers with attachments in filament', function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $inquiry = Inquiry::create([
        'title' => '¿Dónde descargo el formato de préstamo?',
        'body' => 'Necesito el formato oficial.',
        'category' => 'Prestaciones',
        'is_public' => true,
        'guest_name' => 'Docente',
    ]);

    Livewire::test(ListInquiries::class)
        ->assertCanSeeTableRecords([$inquiry]);

    $pdf = UploadedFile::fake()->create('formato-prestamo-2026.pdf', 300, 'application/pdf');

    Livewire::test(AnswersRelationManager::class, [
        'ownerRecord' => $inquiry,
        'pageClass' => EditInquiry::class,
    ])
        ->callTableAction('create', data: [
            'body' => 'Puedes descargar el formato adjunto en este mensaje.',
            'is_official' => true,
            'attachments' => [$pdf],
        ])
        ->assertHasNoTableActionErrors();

    $inquiry->refresh();
    expect($inquiry->status)->toBe(InquiryStatus::Answered);
    expect($inquiry->answers()->count())->toBe(1);

    $answer = $inquiry->answers()->first();
    expect($answer->is_official)->toBeTrue();
    expect($answer->hasMedia('attachments'))->toBeTrue();

    // Check public download of answer attachment
    $media = $answer->getFirstMedia('attachments');
    $this->get(route('inquiries.attachment.download', $media))
        ->assertOk();
});
