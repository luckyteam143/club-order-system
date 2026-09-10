<?php

namespace App\Mail;

use App\Models\OrderNote;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderNoteAddedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public OrderNote $note, public string $viewUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Note on Order #{$this->note->order_id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.note-added',
            with: [
                'note' => $this->note,
                'url'  => $this->viewUrl,
            ],
        );
    }
}
