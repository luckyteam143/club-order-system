<?php

namespace App\Support;

use App\Mail\OrderNoteAddedMail;
use App\Mail\OrderStatusChangedMail;
use App\Mail\OrderSubmittedMail;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Central place for order-related email triggers, so recipient logic and
 * the mail_send_enabled/per-module gates live in one spot rather than
 * scattered across the resource/widget/observer that fire them. Every
 * send is best-effort — a mail failure must never break the order action
 * (submit, status update, note) that triggered it.
 */
class OrderNotifier
{
    public static function submitted(Order $order): void
    {
        if (! self::enabled('notify_order_submitted')) {
            return;
        }

        self::send(self::adminRecipients(), new OrderSubmittedMail($order));
    }

    public static function statusChanged(Order $order, string $oldStatus, string $newStatus): void
    {
        if (Setting::get('mail_send_enabled', 'false') !== 'true') {
            return;
        }

        $enabledStatuses = json_decode(Setting::get('notify_status_changed_statuses', '[]'), true) ?: [];

        if (! in_array($newStatus, $enabledStatuses, true)) {
            return;
        }

        $recipient = $order->contactDetails()['email'];

        if (blank($recipient)) {
            return;
        }

        self::send([$recipient], new OrderStatusChangedMail($order, $oldStatus, $newStatus));
    }

    public static function noteAdded(OrderNote $note): void
    {
        if (! self::enabled('notify_order_note_added')) {
            return;
        }

        $note->loadMissing(['user', 'order.club', 'order.clubTeam']);
        $author = $note->user;
        $order = $note->order;

        if (! $author || ! $order) {
            return;
        }

        // Notify whichever side didn't write the note — staff notes go to
        // the club, club notes go to admin.
        if ($author->isAdmin() || $author->isSubAdmin()) {
            $recipients = array_filter([$order->contactDetails()['email']]);
            $viewUrl = route('filament.clubs.resources.orders.view', $order);
        } elseif ($author->isClub()) {
            $recipients = self::adminRecipients();
            $viewUrl = route('filament.admin.resources.orders.view', $order);
        } else {
            return;
        }

        if (empty($recipients)) {
            return;
        }

        self::send($recipients, new OrderNoteAddedMail($note, $viewUrl));
    }

    private static function adminRecipients(): array
    {
        return collect(explode(',', (string) Setting::get('admin_notification_emails', '')))
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->values()
            ->all();
    }

    private static function enabled(string $moduleKey): bool
    {
        return Setting::get('mail_send_enabled', 'false') === 'true'
            && Setting::get($moduleKey, 'true') === 'true';
    }

    private static function send(array $recipients, \Illuminate\Mail\Mailable $mailable): void
    {
        $recipients = array_filter($recipients);

        if (empty($recipients)) {
            return;
        }

        try {
            Mail::to($recipients)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Order notification email failed: '.$e->getMessage(), ['mailable' => $mailable::class, 'recipients' => $recipients]);
        }
    }
}
