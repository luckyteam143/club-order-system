<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Per-module notification toggles, layered on top of the master
     * mail_send_enabled switch — so an admin can e.g. keep low-stock alerts
     * on but turn off note notifications without touching the others.
     * All default "on" since the master switch is what actually gates
     * sending while it's off.
     */
    public function up(): void
    {
        $rows = [
            'admin_notification_emails'   => 'orders@macronstore.ca',
            'notify_order_submitted'      => 'true',
            'notify_order_status_changed' => 'true',
            'notify_order_note_added'     => 'true',
            'notify_low_stock'            => 'true',
            'low_stock_threshold'         => '5',
        ];

        foreach ($rows as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'admin_notification_emails', 'notify_order_submitted', 'notify_order_status_changed',
            'notify_order_note_added', 'notify_low_stock', 'low_stock_threshold',
        ])->delete();
    }
};
