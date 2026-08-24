<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replaces the single notify_order_status_changed on/off toggle with a
     * per-status list (JSON array of status keys, same pattern as
     * log_retention_by_module) — an admin can e.g. get an email for
     * "Shipped" without one for every "In Production" tweak. Defaults to
     * every status except draft/admin_edit, which are internal-only and
     * never meant to reach a club's inbox.
     */
    public function up(): void
    {
        $defaultStatuses = [
            'submitted', 'pending', 'received', 'in_production', 'partially_ready',
            'partially_picked', 'partially_shipped', 'shipped', 'completed',
            'cancelled', 'forecast_submitted',
        ];

        DB::table('settings')->updateOrInsert(
            ['key' => 'notify_status_changed_statuses'],
            ['value' => json_encode($defaultStatuses), 'created_at' => now(), 'updated_at' => now()],
        );

        DB::table('settings')->where('key', 'notify_order_status_changed')->delete();
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'notify_status_changed_statuses')->delete();

        DB::table('settings')->updateOrInsert(
            ['key' => 'notify_order_status_changed'],
            ['value' => 'true', 'created_at' => now(), 'updated_at' => now()],
        );
    }
};
