<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_product', function (Blueprint $table) {
            // Colour of the printed player number for this item (e.g. "Navy",
            // "White"). Drives the per-colour split of the Player Number
            // Digit Count in the order export.
            $table->string('number_color', 50)->nullable()->after('is_player_item');
        });
    }

    public function down(): void
    {
        Schema::table('package_product', function (Blueprint $table) {
            $table->dropColumn('number_color');
        });
    }
};
