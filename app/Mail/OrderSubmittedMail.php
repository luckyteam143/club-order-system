<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Order Submitted — Order #{$this->order->id} ({$this->order->club?->name})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.submitted',
            with: [
                'order' => $this->order,
                'url'   => route('filament.admin.resources.orders.view', $this->order),
            ],
        );
    }
}
