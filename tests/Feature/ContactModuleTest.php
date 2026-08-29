<?php

use App\Enums\ContactSubmissionStatus;
use App\Enums\UserRole;
use App\Filament\Resources\ContactSubmissions\Pages\ListContactSubmissions;
use App\Filament\Resources\ContactSubmissions\Pages\ViewContactSubmission;
use App\Mail\ContactSubmissionAdminMail;
use App\Mail\ContactSubmissionReplyMail;
use App\Models\ContactSubmission;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('contact form can be submitted and notifies admin', function () {
    Mail::fake();

    $response = $this->post('/contact', [
        'name' => 'Profesor Ejemplo',
        'email' => 'profesor@ejemplo.com',
        'phone' => '9361234567',
        'subject' => 'Consulta de escalafón',
        'message' => 'Tengo una duda sobre mi proceso de escalafón docente.',
    ]);

    $response->assertSessionHas('success');

    $this->assertDatabaseHas('contact_submissions', [
        'name' => 'Profesor Ejemplo',
        'email' => 'profesor@ejemplo.com',
        'phone' => '9361234567',
        'status' => 'pending',
    ]);

    Mail::assertQueued(ContactSubmissionAdminMail::class, function ($mail) {
        return $mail->submission->email === 'profesor@ejemplo.com';
    });
});

test('contact form validation rules', function () {
    $response = $this->post('/contact', [
        'name' => '',
        'email' => 'correo-invalido',
        'message' => '',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'message']);
});

test('admin can view and reply to a contact submission in filament', function () {
    Mail::fake();
    Filament::setCurrentPanel(Filament::getPanel('portal'));

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $this->actingAs($admin);

    $submission = ContactSubmission::create([
        'name' => 'Juan Solicitante',
        'email' => 'juan@solicitante.com',
        'phone' => '9361112233',
        'subject' => 'Duda sobre cuotas',
        'message' => '¿Cuándo se publica el reporte?',
        'status' => ContactSubmissionStatus::Pending,
    ]);

    // Admin views the list
    Livewire::test(ListContactSubmissions::class)
        ->assertCanSeeTableRecords([$submission]);

    // Admin opens view page and replies
    Livewire::test(ViewContactSubmission::class, ['record' => $submission->id])
        ->callAction('reply', data: [
            'message' => 'Estimado Juan, el reporte se publicará este viernes.',
        ])
        ->assertHasNoActionErrors();

    $submission->refresh();
    expect($submission->status)->toBe(ContactSubmissionStatus::Replied);
    expect($submission->replies()->count())->toBe(1);

    $this->assertDatabaseHas('contact_replies', [
        'contact_submission_id' => $submission->id,
        'user_id' => $admin->id,
        'message' => 'Estimado Juan, el reporte se publicará este viernes.',
    ]);

    Mail::assertQueued(ContactSubmissionReplyMail::class, function ($mail) {
        return $mail->submission->email === 'juan@solicitante.com'
            && str_contains($mail->reply->message, 'este viernes');
    });
});

test('contact submission admin mail renders with institutional theme styles', function () {
    $submission = ContactSubmission::create([
        'name' => 'Profesor Prueba',
        'email' => 'profesor@ejemplo.com',
        'phone' => '9361234567',
        'subject' => 'Consulta de escalafón',
        'message' => 'Tengo una duda sobre mi proceso de escalafón docente.',
        'status' => ContactSubmissionStatus::Pending,
    ]);

    $mailable = new ContactSubmissionAdminMail($submission);
    $html = $mailable->render();

    expect($html)
        ->toContain('#611232')
        ->toContain('#a57f2c')
        ->toContain('Nuevo Mensaje de Contacto')
        ->toContain('Profesor Prueba')
        ->toContain('profesor@ejemplo.com')
        ->toContain('Consulta de escalafón')
        ->toContain('Ver en el Portal');
});

test('contact submission reply mail renders with institutional theme styles', function () {
    $submission = ContactSubmission::create([
        'name' => 'Juan Solicitante',
        'email' => 'juan@solicitante.com',
        'phone' => '9361112233',
        'subject' => 'Duda sobre cuotas',
        'message' => '¿Cuándo se publica el reporte?',
        'status' => ContactSubmissionStatus::Replied,
    ]);

    $reply = $submission->replies()->create([
        'user_id' => User::factory()->create(['role' => UserRole::Admin])->id,
        'message' => 'Estimado Juan, el reporte se publicará este viernes.',
    ]);

    $mailable = new ContactSubmissionReplyMail($submission, $reply);
    $html = $mailable->render();

    expect($html)
        ->toContain('#611232')
        ->toContain('#a57f2c')
        ->toContain('OST SIUT ITSM')
        ->toContain('Estimado(a) Juan Solicitante')
        ->toContain('Estimado Juan, el reporte se publicará este viernes.');
});
