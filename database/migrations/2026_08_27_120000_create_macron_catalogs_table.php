<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('macron_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // String, not year(): allows spanning seasons like "2026/27".
            $table->string('year', 9);
            $table->string('link', 2048);
            // When true this catalog gets a link in the panel sidebar (still
            // only shown to users with the view_macron_catalogs permission).
            $table->boolean('show_in_menu')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('macron_catalogs');
    }
};
