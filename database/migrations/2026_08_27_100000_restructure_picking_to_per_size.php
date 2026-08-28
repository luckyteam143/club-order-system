<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Picking was originally tracked per OrderItem (one barcode per item),
     * but sizes are actually separate catalog products with their own
     * barcode and stock (parent_sku on the size variant = the parent
     * item's default_sku) — an item-level barcode can never confirm which
     * physical size is being picked. Moves pick tracking to a new
     * order_item_picks table keyed by (order_item_id, size) instead, which
     * also matches the size grouping already used for display/export.
     * No picking data exists yet (feature shipped same day), so this is a
     * clean structural move, not a data migration.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('picked_by');
            $table->dropColumn(['picked_qty', 'picking_note', 'picked_at']);
        });

        Schema::create('order_item_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('size');
            $table->unsignedInteger('picked_qty')->default(0);
            $table->text('picking_note')->nullable();
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->timestamps();

            $table->unique(['order_item_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_picks');

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('picked_qty')->default(0)->after('notes');
            $table->text('picking_note')->nullable()->after('picked_qty');
            $table->foreignId('picked_by')->nullable()->after('picking_note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable()->after('picked_by');
        });
    }
};
