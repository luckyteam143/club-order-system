<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_product', function (Blueprint $table) {
            $table->boolean('is_goalie_item')->default(false)->after('crest_number');
            $table->boolean('is_player_item')->default(true)->after('is_goalie_item');
        });
    }

    public function down(): void
    {
        Schema::table('package_product', function (Blueprint $table) {
            $table->dropColumn(['is_goalie_item', 'is_player_item']);
        });
    }
};
