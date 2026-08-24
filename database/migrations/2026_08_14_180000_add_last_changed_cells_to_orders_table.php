<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which roster grid cells (row/item pairs, as "p{row_id}:i{item_id}"
     * keys) were touched by the most recent post-submission save — lets the
     * grid highlight exactly what changed without re-diffing on every load.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('last_changed_cells')->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('last_changed_cells');
        });
    }
};
