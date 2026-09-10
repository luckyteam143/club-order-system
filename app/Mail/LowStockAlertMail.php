<?php

namespace App\Mail;

use App\Models\LogoStock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public LogoStock $logoStock, public int $threshold) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Low Stock Alert — {$this->logoStock->logo_name} ({$this->logoStock->barcode})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.logo-stock.low-stock',
            with: [
                'logoStock' => $this->logoStock,
                'threshold' => $this->threshold,
                'url'       => route('filament.admin.resources.logo-stocks.edit', $this->logoStock),
            ],
        );
    }
}
