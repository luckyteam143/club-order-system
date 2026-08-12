<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_product', function (Blueprint $table) {
            $table->decimal('club_price', 10, 2)->nullable()->after('product_id');
            $table->decimal('online_store_price', 10, 2)->nullable()->after('club_price');
        });
    }

    public function down(): void
    {
        Schema::table('club_product', function (Blueprint $table) {
            $table->dropColumn(['club_price', 'online_store_price']);
        });
    }
};
