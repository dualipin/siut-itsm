<?php

use App\Enums\ConversationStatus;
use App\Enums\UserRole;
use App\Filament\Pages\Messages;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('public');
});

test('user can start a new conversation and send messages with attachments', function () {
    $agremiado = User::factory()->create([
        'name' => 'Carlos',
        'role' => UserRole::Agremiado,
    ]);

    $lider = User::factory()->create([
        'name' => 'Roberto',
        'role' => UserRole::Lider,
    ]);

    $this->actingAs($agremiado);

    $attachment = UploadedFile::fake()->create('documento-solicitud.pdf', 500, 'application/pdf');

    Livewire::test(Messages::class)
        ->set('newRecipientId', $lider->id)
        ->set('newSubject', 'Solicitud de apoyo gremial')
        ->set('newInitialMessage', 'Hola estimado líder, adjunto mi solicitud.')
        ->set('newAttachment', $attachment)
        ->call('createConversation')
        ->assertHasNoErrors();

    $conversation = Conversation::first();
    expect($conversation)->not->toBeNull();
    expect($conversation->subject)->toBe('Solicitud de apoyo gremial');
    expect($conversation->hasParticipant($agremiado))->toBeTrue();
    expect($conversation->hasParticipant($lider))->toBeTrue();

    // Check message and attachment
    $message = $conversation->messages()->first();
    expect($message)->not->toBeNull();
    expect($message->body)->toBe('Hola estimado líder, adjunto mi solicitud.');
    expect($message->hasMedia('attachments'))->toBeTrue();

    // Leader opens conversation and sends reply
    $this->actingAs($lider);

    Livewire::test(Messages::class, ['conversation' => $conversation->id])
        ->set('newMessageBody', 'Enterado, reviso el documento a la brevedad.')
        ->call('sendMessage')
        ->assertHasNoErrors();

    expect($conversation->messages()->count())->toBe(2);
});

test('user cannot start conversation with himself', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    Livewire::test(Messages::class)
        ->set('newRecipientId', $admin->id)
        ->set('newSubject', 'Auto mensaje')
        ->set('newInitialMessage', 'Prueba')
        ->call('createConversation');

    expect(Conversation::count())->toBe(0);
});

test('conversation can be toggled closed and reopened', function () {
    $userA = User::factory()->create(['role' => UserRole::Admin]);
    $userB = User::factory()->create(['role' => UserRole::Agremiado]);

    $conv = Conversation::create([
        'subject' => 'Tema cerrado',
        'created_by' => $userA->id,
        'status' => ConversationStatus::Active,
        'last_message_at' => now(),
    ]);

    ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $userA->id]);
    ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $userB->id]);

    $this->actingAs($userA);

    Livewire::test(Messages::class, ['conversation' => $conv->id])
        ->call('toggleConversationStatus');

    $conv->refresh();
    expect($conv->status)->toBe(ConversationStatus::Closed);

    Livewire::test(Messages::class, ['conversation' => $conv->id])
        ->call('toggleConversationStatus');

    $conv->refresh();
    expect($conv->status)->toBe(ConversationStatus::Active);
});

test('non-participants cannot download conversation attachments', function () {
    $userA = User::factory()->create(['role' => UserRole::Admin]);
    $userB = User::factory()->create(['role' => UserRole::Agremiado]);
    $stranger = User::factory()->create(['role' => UserRole::Agremiado]);

    $conv = Conversation::create([
        'subject' => 'Privado',
        'created_by' => $userA->id,
        'status' => ConversationStatus::Active,
    ]);
    ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $userA->id]);
    ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $userB->id]);

    $msg = ConversationMessage::create([
        'conversation_id' => $conv->id,
        'sender_id' => $userA->id,
        'body' => 'Archivo confidencial',
    ]);

    $file = UploadedFile::fake()->create('privado.pdf', 100);
    $media = $msg->addMedia($file->getRealPath())->usingFileName('privado.pdf')->toMediaCollection('attachments');

    // Stranger tries to download
    $this->actingAs($stranger)
        ->get(route('portal.messages.attachment.download', $media))
        ->assertForbidden();

    // Participant can download
    $this->actingAs($userB)
        ->get(route('portal.messages.attachment.download', $media))
        ->assertOk();
});

test('messages page provides formatted searchable recipients list for new conversation modal', function () {
    $agremiado = User::factory()->create([
        'name' => 'María',
        'surnames' => 'López',
        'email' => 'maria@example.com',
        'role' => UserRole::Agremiado,
    ]);

    $lider = User::factory()->create([
        'name' => 'Roberto',
        'surnames' => 'Gómez',
        'email' => 'roberto@example.com',
        'role' => UserRole::Lider,
        'category' => 'Docente',
    ]);

    $admin = User::factory()->create([
        'name' => 'Ana',
        'surnames' => 'Martínez',
        'email' => 'ana@example.com',
        'role' => UserRole::Admin,
    ]);

    $inactive = User::factory()->create([
        'name' => 'Inactivo',
        'is_active' => false,
        'role' => UserRole::Lider,
    ]);

    $this->actingAs($agremiado);

    $component = Livewire::test(Messages::class);

    $recipients = $component->get('recipientsList');

    expect($recipients)->toBeArray();
    expect(count($recipients))->toBe(2); // Should include lider and admin, not self and not inactive

    $liderRecipient = collect($recipients)->firstWhere('id', $lider->id);
    expect($liderRecipient)->not->toBeNull();
    expect($liderRecipient['full_name'])->toBe('Roberto Gómez');
    expect($liderRecipient['email'])->toBe('roberto@example.com');
    expect($liderRecipient['role_label'])->toBe('Líder');
    expect($liderRecipient['category'])->toBe('Docente');
    expect($liderRecipient['initials'])->toBe('RO');

    // Agremiado should not be able to contact other agremiados
    $otherAgremiado = User::factory()->create(['role' => UserRole::Agremiado]);
    $updatedRecipients = $component->get('recipientsList');
    expect(collect($updatedRecipients)->firstWhere('id', $otherAgremiado->id))->toBeNull();

    // Admin can see everyone (agremiados, leaders, other admins)
    $this->actingAs($admin);
    $adminComponent = Livewire::test(Messages::class);
    $adminRecipients = $adminComponent->get('recipientsList');

    expect(collect($adminRecipients)->firstWhere('id', $otherAgremiado->id))->not->toBeNull();
    expect(collect($adminRecipients)->firstWhere('id', $agremiado->id))->not->toBeNull();
});
