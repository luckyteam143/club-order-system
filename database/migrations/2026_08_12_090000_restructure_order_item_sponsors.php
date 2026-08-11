<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sponsor_logo_id');
            $table->dropConstrainedForeignId('embellishment_id');
        });

        Schema::create('order_item_sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsor_logo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('embellishment_position_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_sponsors');

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('sponsor_logo_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('embellishment_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
