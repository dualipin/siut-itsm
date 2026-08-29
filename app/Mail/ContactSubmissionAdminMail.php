<?php

namespace App\Mail;

use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactSubmissionAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactSubmission $submission
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->submission->subject
            ? "Nuevo mensaje de contacto: {$this->submission->subject}"
            : "Nuevo mensaje de contacto de {$this->submission->name}";

        return new Envelope(
            subject: $subject,
            replyTo: [$this->submission->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-submission-admin',
        );
    }
}
