<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embellishments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('cost', 10, 2)->default(0);
            $table->foreignId('embellishment_position_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embellishments');
    }
};
