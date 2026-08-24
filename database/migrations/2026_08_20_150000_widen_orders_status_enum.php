<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * orders.status was enum('draft','submitted','admin_edit','completed')
     * but OrderResource::STATUSES offers 13 values in the Edit form's
     * dropdown — picking any of the missing ones threw a raw "Data
     * truncated" MySQL error on save. Widened to match STATUSES exactly.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY status ENUM(
            'draft', 'submitted', 'pending', 'received', 'in_production',
            'partially_ready', 'partially_picked', 'partially_shipped', 'shipped',
            'completed', 'cancelled', 'admin_edit', 'forecast_submitted'
        ) NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Any order already sitting in one of the newly-added statuses has
        // to move somewhere the original 4-value enum still accepts, or
        // the ALTER below would itself fail with the same truncation error.
        DB::table('orders')
            ->whereNotIn('status', ['draft', 'submitted', 'admin_edit', 'completed'])
            ->update(['status' => 'admin_edit']);

        DB::statement("ALTER TABLE orders MODIFY status ENUM(
            'draft', 'submitted', 'admin_edit', 'completed'
        ) NOT NULL DEFAULT 'draft'");
    }
};
