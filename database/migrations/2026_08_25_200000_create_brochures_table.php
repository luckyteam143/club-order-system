<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brochures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('link');
            // Named created_date/approved_date (not created_at) — these are
            // the brochure's own business dates, distinct from the row's
            // own created_at/updated_at bookkeeping timestamps below.
            $table->date('created_date');
            $table->date('approved_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brochures');
    }
};
