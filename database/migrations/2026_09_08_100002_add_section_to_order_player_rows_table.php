<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which roster block a player row belongs to on the Order grid —
     * 'player' (the default, existing rows included) or 'goalie'. Only
     * meaningful for Package orders whose package has goalkeeper items;
     * every other order type's rows just stay 'player'.
     */
    public function up(): void
    {
        Schema::table('order_player_rows', function (Blueprint $table) {
            $table->string('section')->default('player')->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('order_player_rows', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
};
