<?php

use App\Enums\LoanGeneratedDocumentType;
use App\Enums\UserDocumentType;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Loan;
use App\Models\LoanEvidence;
use App\Models\LoanGeneratedDocument;
use App\Models\TransparencyDocument;
use App\Models\TransparencyRecord;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('local');
    Storage::fake('public');
});

test('inactive user cannot access the panel and is blocked across all download endpoints', function () {
    $inactiveUser = User::factory()->create([
        'is_active' => false,
        'role' => UserRole::Agremiado,
    ]);

    expect($inactiveUser->canAccessPanel(Filament::getPanel('portal')))->toBeFalse();

    $this->actingAs($inactiveUser);

    // Panel root
    $this->get('/portal')->assertForbidden();

    // Transparency documents download
    $record = TransparencyRecord::factory()->published()->create();
    $doc = TransparencyDocument::create([
        'transparency_record_id' => $record->id,
        'name' => 'Doc Inactivo',
        'published_at' => now(),
        'is_public' => true,
        'uploaded_by' => $inactiveUser->id,
    ]);
    $doc->addMedia(UploadedFile::fake()->createWithContent('file.pdf', '%PDF-1.4'))
        ->setFileName('file.pdf')
        ->toMediaCollection('file');

    $this->get(route('portal.transparency.documents.show', $doc))->assertForbidden();

    // User affiliation document
    $this->get(route('portal.users.documents.show', ['user' => $inactiveUser, 'type' => UserDocumentType::Afiliacion->value]))
        ->assertForbidden();

    // Message attachment
    $conv = Conversation::create(['subject' => 'Test', 'created_by' => $inactiveUser->id]);
    ConversationParticipant::create(['conversation_id' => $conv->id, 'user_id' => $inactiveUser->id]);
    $msg = ConversationMessage::create(['conversation_id' => $conv->id, 'sender_id' => $inactiveUser->id, 'body' => 'Hola']);
    $media = $msg->addMedia(UploadedFile::fake()->createWithContent('att.pdf', '%PDF-1.4'))
        ->setFileName('att.pdf')
        ->toMediaCollection('attachments');

    $this->get(route('portal.messages.attachment.download', $media))->assertForbidden();

    // Loan generated document
    $loan = Loan::factory()->create(['user_id' => $inactiveUser->id]);
    Storage::disk('local')->put('private/loans/loan.pdf', '%PDF-1.4 loan');
    $loanDoc = LoanGeneratedDocument::create([
        'loan_id' => $loan->id,
        'document_type' => LoanGeneratedDocumentType::Solicitud,
        'file_path' => 'private/loans/loan.pdf',
        'file_hash' => hash('sha256', '%PDF-1.4 loan'),
        'file_name' => 'loan.pdf',
        'file_size' => 100,
        'mime_type' => 'application/pdf',
        'version' => 1,
        'generated_at' => now(),
    ]);

    $this->get(route('loans.documents.download', ['loan' => $loan, 'document' => $loanDoc]))
        ->assertForbidden();

    // Loan evidence download
    Storage::disk('local')->put('private/loans/ev.pdf', '%PDF-1.4 ev');
    $evidence = LoanEvidence::create([
        'loan_id' => $loan->id,
        'source_type' => 'benefit',
        'file_path' => 'private/loans/ev.pdf',
        'file_hash' => hash('sha256', '%PDF-1.4 ev'),
        'file_name' => 'ev.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 100,
        'manual_review_status' => 'pending',
    ]);

    $this->get(route('loans.evidences.download', ['loan' => $loan, 'evidence' => $evidence]))
        ->assertForbidden();
});
