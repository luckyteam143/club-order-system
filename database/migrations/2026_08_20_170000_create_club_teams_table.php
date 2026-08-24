<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('team_name');
            $table->string('po_reference')->nullable();
            $table->string('coach_manager_name')->nullable();
            $table->string('coach_manager_email')->nullable();
            $table->string('coach_manager_contact')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['club_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_teams');
    }
};
