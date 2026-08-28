<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `picking_note` is kept separate from the existing customer-facing
     * `notes` column so a picker's note never overwrites what was entered
     * at order-creation time.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('picked_qty')->default(0)->after('notes');
            $table->text('picking_note')->nullable()->after('picked_qty');
            $table->foreignId('picked_by')->nullable()->after('picking_note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable()->after('picked_by');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('picked_by');
            $table->dropColumn(['picked_qty', 'picking_note', 'picked_at']);
        });
    }
};
