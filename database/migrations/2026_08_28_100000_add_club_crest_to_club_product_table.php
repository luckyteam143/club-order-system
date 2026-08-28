<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_product', function (Blueprint $table) {
            $table->boolean('has_club_crest')->default(true)->after('online_store_price');
            $table->unsignedTinyInteger('crest_number')->default(1)->after('has_club_crest');
        });
    }

    public function down(): void
    {
        Schema::table('club_product', function (Blueprint $table) {
            $table->dropColumn(['has_club_crest', 'crest_number']);
        });
    }
};
