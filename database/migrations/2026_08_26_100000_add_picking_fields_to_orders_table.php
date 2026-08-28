<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliberately separate from the customer-facing `status` column (which
     * already has a `partially_picked` value clubs can see) — picking state
     * must stay internal-only, so it lives here instead.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('picking_status')->default('not_picked')->after('status');
            $table->foreignId('picking_assigned_to')->nullable()->after('picking_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('picking_sent_at')->nullable()->after('picking_assigned_to');
            $table->timestamp('picking_completed_at')->nullable()->after('picking_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('picking_assigned_to');
            $table->dropColumn(['picking_status', 'picking_sent_at', 'picking_completed_at']);
        });
    }
};
