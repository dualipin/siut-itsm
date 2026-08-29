<?php

namespace App\Mail;

use App\Models\ContactReply;
use App\Models\ContactSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactSubmissionReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactSubmission $submission,
        public ContactReply $reply
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->submission->subject
            ? "Re: {$this->submission->subject} - Respuesta del Sindicato OST SIUT ITSM"
            : 'Respuesta a tu mensaje - Sindicato OST SIUT ITSM';

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-submission-reply',
        );
    }
}
