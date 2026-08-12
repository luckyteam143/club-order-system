<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_sponsors', function (Blueprint $table) {
            $table->string('brochure_link')->nullable()->after('embellishment_position_id');
            $table->decimal('override_price', 10, 2)->nullable()->after('brochure_link');
        });

        Schema::table('order_item_embellishments', function (Blueprint $table) {
            $table->decimal('override_price', 10, 2)->nullable()->after('embellishment_position_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_item_sponsors', function (Blueprint $table) {
            $table->dropColumn(['brochure_link', 'override_price']);
        });

        Schema::table('order_item_embellishments', function (Blueprint $table) {
            $table->dropColumn('override_price');
        });
    }
};
