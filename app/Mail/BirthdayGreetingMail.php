<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BirthdayGreetingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param  list<string>  $greetingTags
     */
    public function __construct(
        public User $user,
        public string $messageContent,
        public array $greetingTags = [],
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $sender = config('syndicate.acronym') ?: config('app.name');

        return new Envelope(
            subject: "🎂 ¡Feliz Cumpleaños, {$this->user->name}! 🎉 - {$sender}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.birthday-greeting',
        );
    }
}
