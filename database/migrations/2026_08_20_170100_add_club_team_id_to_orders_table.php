<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Nullable + nullOnDelete — a deleted team shouldn't take its
            // historical orders down with it, just detach the reference.
            $table->foreignId('club_team_id')->nullable()->after('club_id')
                ->constrained('club_teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('club_team_id');
        });
    }
};
