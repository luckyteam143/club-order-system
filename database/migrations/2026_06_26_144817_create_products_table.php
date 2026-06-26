<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->nullable()->unique();
            $table->string('parent_sku')->nullable();
            $table->string('default_sku')->nullable();
            $table->string('size')->nullable();
            $table->string('name');
            $table->unsignedInteger('qty')->default(0);
            $table->boolean('on_backorder')->default(false);
            $table->date('backorder_date')->nullable();
            $table->decimal('retail_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
