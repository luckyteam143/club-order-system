<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_product_sponsors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_product_id')->constrained('package_product')->cascadeOnDelete();
            $table->foreignId('sponsor_logo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('embellishment_position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brochure_link')->nullable();
            $table->decimal('override_price', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('package_product_embellishments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_product_id')->constrained('package_product')->cascadeOnDelete();
            $table->foreignId('embellishment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('embellishment_position_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('override_price', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_product_embellishments');
        Schema::dropIfExists('package_product_sponsors');
    }
};
