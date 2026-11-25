<?php

use App\Enums\ConversationStatus;
use App\Enums\DocumentStatus;
use App\Enums\LoanStatus;
use App\Enums\RequestStatus;
use App\Enums\UserDocumentType;
use App\Filament\Pages\Messages;
use App\Filament\Pages\Profile;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\UserRequests\UserRequestResource;
use App\Filament\Widgets\AgremiadoStatsWidget;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationParticipant;
use App\Models\Loan;
use App\Models\LoanAmortizationRow;
use App\Models\User;
use App\Models\UserRequest;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('portal'));
    Storage::fake('local');
    Mail::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

test('only agremiado can view agremiado stats widget on portal dashboard', function () {
    expect(AgremiadoStatsWidget::canView())->toBeFalse();

    $agremiado = User::factory()->create(['is_active' => true]);
    $this->actingAs($agremiado);

    expect(AgremiadoStatsWidget::canView())->toBeTrue();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertSee('Expediente documental')
        ->assertSee('Préstamo activo')
        ->assertSee('Solicitudes en curso')
        ->assertSee('Mensajes sin leer')
        ->assertSee(Profile::getUrl(), escape: false)
        ->assertSee(LoanResource::getUrl('index'), escape: false)
        ->assertSee(UserRequestResource::getUrl('index'), escape: false)
        ->assertSee(Messages::getUrl(), escape: false);

    $admin = User::factory()->admin()->create(['is_active' => true]);
    $this->actingAs($admin);

    expect(AgremiadoStatsWidget::canView())->toBeFalse();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertDontSee('Expediente documental')
        ->assertDontSee('Solicitudes en curso');

    $lider = User::factory()->lider()->create(['is_active' => true]);
    $this->actingAs($lider);

    expect(AgremiadoStatsWidget::canView())->toBeFalse();

    $this->get('/portal')
        ->assertSuccessful()
        ->assertDontSee('Expediente documental')
        ->assertDontSee('Solicitudes en curso');
});

test('document stat displays valid count out of 5 and overall document status', function () {
    $agremiado = User::factory()->create(['is_active' => true]);
    $this->actingAs($agremiado);

    // 0 of 5 uploaded => Pending upload
    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSuccessful()
        ->assertSee('0/5')
        ->assertSee('Pendiente de subir')
        ->assertSee(Profile::getUrl(), escape: false);

    // 3 valid + 1 invalid + 1 missing => Invalid
    $types = UserDocumentType::cases();
    foreach (array_slice($types, 0, 3) as $type) {
        $agremiado->addMedia(UploadedFile::fake()->create("{$type->value}.pdf", 100, 'application/pdf'))
            ->withCustomProperties(['status' => DocumentStatus::Valid->value])
            ->toMediaCollection($type->value);
    }

    $agremiado->addMedia(UploadedFile::fake()->create("{$types[3]->value}.pdf", 100, 'application/pdf'))
        ->withCustomProperties(['status' => DocumentStatus::Invalid->value])
        ->toMediaCollection($types[3]->value);

    $this->actingAs($agremiado->fresh());

    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSee('3/5')
        ->assertSee('Inválido');

    // All 5 uploaded (4 valid + 1 pending) => Pending review
    $agremiado->fresh()->getDocumentMedia($types[3])?->setCustomProperty('status', DocumentStatus::Pending->value)->save();
    $agremiado->addMedia(UploadedFile::fake()->create("{$types[4]->value}.pdf", 100, 'application/pdf'))
        ->withCustomProperties(['status' => DocumentStatus::Valid->value])
        ->toMediaCollection($types[4]->value);

    $this->actingAs($agremiado->fresh());

    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSee('4/5')
        ->assertSee('Pendiente de Revisión');

    // All 5 valid => Valid
    $agremiado->fresh()->getDocumentMedia($types[3])?->setCustomProperty('status', DocumentStatus::Valid->value)->save();

    $this->actingAs($agremiado->fresh());

    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSee('5/5')
        ->assertSee('Válido');
});

test('loan stat displays no active loan when user only has rejected or other users loans', function () {
    $agremiado = User::factory()->create(['is_active' => true]);
    $otherUser = User::factory()->create(['is_active' => true]);

    Loan::factory()->create([
        'user_id' => $agremiado->id,
        'status' => LoanStatus::Rechazada,
        'requested_amount' => 9000.00,
    ]);

    Loan::factory()->approved()->create([
        'user_id' => $otherUser->id,
        'approved_amount' => 18000.00,
    ]);

    $this->actingAs($agremiado);

    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSee('Sin préstamo activo')
        ->assertSee(LoanResource::getUrl('index'), escape: false)
        ->assertDontSee('$18,000.00');
});

test('loan stat displays active approved loan status, amount and record url', function () {
    $agremiado = User::factory()->create(['is_active' => true]);

    $olderLoan = Loan::factory()->submitted()->create([
        'user_id' => $agremiado->id,
        'requested_amount' => 5000.00,
    ]);

    $latestLoan = Loan::factory()->approved()->create([
        'user_id' => $agremiado->id,
        'requested_amount' => 12500.00,
        'approved_amount' => 12500.00,
    ]);

    $this->actingAs($agremiado);

    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSee('Aprobada')
        ->assertSee("{$latestLoan->folio} · \$12,500.00")
        ->assertSee(LoanResource::getUrl('view', ['record' => $latestLoan]), escape: false)
        ->assertDontSee('$5,000.00');
});

test('loan stat displays next amortization payment date and amount for disbursed loan', function () {
    Carbon::setTestNow('2026-06-10 10:00:00');

    $agremiado = User::factory()->create(['is_active' => true]);

    $loan = Loan::factory()->disbursed()->create([
        'user_id' => $agremiado->id,
        'approved_amount' => 20000.00,
        'disbursed_amount' => 20000.00,
    ]);

    // Past payment (must be ignored)
    LoanAmortizationRow::create([
        'loan_id' => $loan->id,
        'period' => 1,
        'type' => 'nomina',
        'label' => 'Quincena 1',
        'date' => '2026-05-30',
        'opening_balance' => 20000.00,
        'principal' => 2000.00,
        'interest' => 500.00,
        'payment' => 2500.00,
        'closing_balance' => 18000.00,
    ]);

    // Next upcoming payment (must be shown)
    LoanAmortizationRow::create([
        'loan_id' => $loan->id,
        'period' => 2,
        'type' => 'nomina',
        'label' => 'Quincena 2',
        'date' => '2026-06-15',
        'opening_balance' => 18000.00,
        'principal' => 2150.00,
        'interest' => 450.00,
        'payment' => 2600.00,
        'closing_balance' => 15850.00,
    ]);

    // Later future payment (must not override nearest)
    LoanAmortizationRow::create([
        'loan_id' => $loan->id,
        'period' => 3,
        'type' => 'nomina',
        'label' => 'Quincena 3',
        'date' => '2026-06-30',
        'opening_balance' => 15850.00,
        'principal' => 2200.00,
        'interest' => 400.00,
        'payment' => 2700.00,
        'closing_balance' => 13650.00,
    ]);

    $this->actingAs($agremiado);

    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSee('Desembolsada')
        ->assertSee("{$loan->folio} · \$20,000.00 · Próx. pago: 15/06/2026 (\$2,600.00)")
        ->assertDontSee('$2,700.00')
        ->assertSee(LoanResource::getUrl('view', ['record' => $loan]), escape: false);
});

test('loan stat selects earliest future amortization date even when a lower period has a later date', function () {
    Carbon::setTestNow('2026-06-10 10:00:00');

    $agremiado = User::factory()->agremiado()->active()->create();

    $loan = Loan::factory()->disbursed()->create([
        'user_id' => $agremiado->id,
        'approved_amount' => 15000.00,
        'disbursed_amount' => 15000.00,
    ]);

    LoanAmortizationRow::create([
        'loan_id' => $loan->id,
        'period' => 2,
        'type' => 'nomina',
        'label' => 'Quincena 2',
        'date' => '2026-06-25',
        'opening_balance' => 15000.00,
        'principal' => 1000.00,
        'interest' => 200.00,
        'payment' => 1200.00,
        'closing_balance' => 14000.00,
    ]);

    LoanAmortizationRow::create([
        'loan_id' => $loan->id,
        'period' => 5,
        'type' => 'nomina',
        'label' => 'Quincena 5',
        'date' => '2026-06-12',
        'opening_balance' => 14000.00,
        'principal' => 800.00,
        'interest' => 150.00,
        'payment' => 950.00,
        'closing_balance' => 13200.00,
    ]);

    $this->actingAs($agremiado);

    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSee("{$loan->folio} · \$15,000.00 · Próx. pago: 12/06/2026 (\$950.00)")
        ->assertDontSee('$1,200.00');
});

test('requests stat counts only own pending and under review requests', function () {
    $agremiado = User::factory()->create(['is_active' => true]);
    $otherUser = User::factory()->create(['is_active' => true]);

    // Counted (2)
    UserRequest::factory()->create([
        'user_id' => $agremiado->id,
        'status' => RequestStatus::Pending,
    ]);
    UserRequest::factory()->create([
        'user_id' => $agremiado->id,
        'status' => RequestStatus::UnderReview,
    ]);

    // Ignored statuses for own user
    UserRequest::factory()->approved()->create(['user_id' => $agremiado->id]);
    UserRequest::factory()->rejected()->create(['user_id' => $agremiado->id]);
    UserRequest::factory()->create([
        'user_id' => $agremiado->id,
        'status' => RequestStatus::Completed,
    ]);
    UserRequest::factory()->create([
        'user_id' => $agremiado->id,
        'status' => RequestStatus::Cancelled,
    ]);

    // Ignored other user's active requests
    UserRequest::factory()->count(4)->create([
        'user_id' => $otherUser->id,
        'status' => RequestStatus::Pending,
    ]);

    $this->actingAs($agremiado);

    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSee('Solicitudes en curso')
        ->assertSee('2')
        ->assertSee(UserRequestResource::getUrl('index'), escape: false);
});

test('unread messages stat counts only unread messages from other participants after last read', function () {
    Carbon::setTestNow('2026-06-10 12:00:00');

    $agremiado = User::factory()->create(['is_active' => true]);
    $admin = User::factory()->admin()->create(['is_active' => true]);
    $stranger = User::factory()->create(['is_active' => true]);

    // Conversation 1: agremiado read at 11:00 -> 1 message before, 2 after from admin, 1 after from agremiado himself
    $conv1 = Conversation::create([
        'subject' => 'Consulta 1',
        'created_by' => $agremiado->id,
        'status' => ConversationStatus::Active,
        'last_message_at' => now(),
    ]);
    ConversationParticipant::create([
        'conversation_id' => $conv1->id,
        'user_id' => $agremiado->id,
        'last_read_at' => Carbon::parse('2026-06-10 11:00:00'),
    ]);
    ConversationParticipant::create([
        'conversation_id' => $conv1->id,
        'user_id' => $admin->id,
    ]);

    $msgRead = ConversationMessage::create([
        'conversation_id' => $conv1->id,
        'sender_id' => $admin->id,
        'body' => 'Ya leído',
    ]);
    $msgRead->forceFill(['created_at' => Carbon::parse('2026-06-10 10:30:00')])->saveQuietly();

    $msgUnread1 = ConversationMessage::create([
        'conversation_id' => $conv1->id,
        'sender_id' => $admin->id,
        'body' => 'No leído 1',
    ]);
    $msgUnread1->forceFill(['created_at' => Carbon::parse('2026-06-10 11:15:00')])->saveQuietly();

    $msgUnread2 = ConversationMessage::create([
        'conversation_id' => $conv1->id,
        'sender_id' => $admin->id,
        'body' => 'No leído 2',
    ]);
    $msgUnread2->forceFill(['created_at' => Carbon::parse('2026-06-10 11:45:00')])->saveQuietly();

    $ownMsg = ConversationMessage::create([
        'conversation_id' => $conv1->id,
        'sender_id' => $agremiado->id,
        'body' => 'Mi propio mensaje',
    ]);
    $ownMsg->forceFill(['created_at' => Carbon::parse('2026-06-10 11:50:00')])->saveQuietly();

    // Conversation 2: agremiado never read (last_read_at = null) -> 1 message from admin counted
    $conv2 = Conversation::create([
        'subject' => 'Consulta 2',
        'created_by' => $admin->id,
        'status' => ConversationStatus::Active,
        'last_message_at' => now(),
    ]);
    ConversationParticipant::create([
        'conversation_id' => $conv2->id,
        'user_id' => $agremiado->id,
        'last_read_at' => null,
    ]);
    ConversationParticipant::create([
        'conversation_id' => $conv2->id,
        'user_id' => $admin->id,
    ]);
    ConversationMessage::create([
        'conversation_id' => $conv2->id,
        'sender_id' => $admin->id,
        'body' => 'No leído en conv 2',
    ]);

    // Conversation 3: agremiado is NOT a participant -> ignored
    $conv3 = Conversation::create([
        'subject' => 'Ajena',
        'created_by' => $admin->id,
        'status' => ConversationStatus::Active,
        'last_message_at' => now(),
    ]);
    ConversationParticipant::create([
        'conversation_id' => $conv3->id,
        'user_id' => $admin->id,
    ]);
    ConversationParticipant::create([
        'conversation_id' => $conv3->id,
        'user_id' => $stranger->id,
    ]);
    ConversationMessage::create([
        'conversation_id' => $conv3->id,
        'sender_id' => $stranger->id,
        'body' => 'Mensaje ajeno',
    ]);

    $this->actingAs($agremiado);

    // Total expected unread = 2 (conv1) + 1 (conv2) = 3
    Livewire::test(AgremiadoStatsWidget::class)
        ->assertSee('Mensajes sin leer')
        ->assertSee('3')
        ->assertSee(Messages::getUrl(), escape: false);
});
