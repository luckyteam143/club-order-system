<?php

namespace App\Mail;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $oldStatus,
        public string $newStatus,
    ) {}

    public function envelope(): Envelope
    {
        $label = OrderResource::STATUSES[$this->newStatus] ?? $this->newStatus;

        return new Envelope(
            subject: "Order #{$this->order->id} Status Updated — {$label}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.status-changed',
            with: [
                'order'     => $this->order,
                'oldLabel'  => OrderResource::STATUSES[$this->oldStatus] ?? $this->oldStatus,
                'newLabel'  => OrderResource::STATUSES[$this->newStatus] ?? $this->newStatus,
                'url'       => route('filament.clubs.resources.orders.view', $this->order),
            ],
        );
    }
}
