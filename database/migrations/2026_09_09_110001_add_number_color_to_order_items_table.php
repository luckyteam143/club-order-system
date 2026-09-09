<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Snapshotted from package_product.number_color when a package
            // order is saved (like has_club_crest / crest_number), so the
            // order export's digit count can group by it.
            $table->string('number_color', 50)->nullable()->after('is_player_item');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('number_color');
        });
    }
};
