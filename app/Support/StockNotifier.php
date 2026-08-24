<?php

namespace App\Support;

use App\Mail\LowStockAlertMail;
use App\Models\LogoStock;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StockNotifier
{
    /**
     * Fires only on the downward crossing of the threshold (previous qty
     * above it, new qty at/below it) — otherwise every scan while stock
     * stays low would re-send the same alert.
     */
    public static function checkLowStock(LogoStock $logoStock, int $previousQty): void
    {
        if (Setting::get('mail_send_enabled', 'false') !== 'true' || Setting::get('notify_low_stock', 'true') !== 'true') {
            return;
        }

        $threshold = (int) Setting::get('low_stock_threshold', 5);

        if ($previousQty <= $threshold || $logoStock->qty > $threshold) {
            return;
        }

        $recipients = collect(explode(',', (string) Setting::get('admin_notification_emails', '')))
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->values()
            ->all();

        if (empty($recipients)) {
            return;
        }

        try {
            Mail::to($recipients)->send(new LowStockAlertMail($logoStock, $threshold));
        } catch (\Throwable $e) {
            Log::error('Low stock alert email failed: '.$e->getMessage(), ['logo_stock_id' => $logoStock->id]);
        }
    }
}
