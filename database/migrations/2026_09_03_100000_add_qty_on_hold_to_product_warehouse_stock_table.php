<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_warehouse_stock', function (Blueprint $table) {
            // Reserved / set-aside stock for this product at this warehouse.
            // Kept as a bucket parallel to `qty` (available on-hand): units
            // moved here are removed from `qty` so they no longer count as
            // sellable, but are still tracked instead of just vanishing.
            $table->unsignedInteger('qty_on_hold')->default(0)->after('qty');
        });
    }

    public function down(): void
    {
        Schema::table('product_warehouse_stock', function (Blueprint $table) {
            $table->dropColumn('qty_on_hold');
        });
    }
};
