<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sponsor_logos', function (Blueprint $table) {
            $table->dropColumn('positions');
            $table->foreignId('embellishment_position_id')->nullable()->after('file')->constrained()->nullOnDelete();
            $table->decimal('price', 10, 2)->default(0)->after('embellishment_position_id');
        });
    }

    public function down(): void
    {
        Schema::table('sponsor_logos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('embellishment_position_id');
            $table->dropColumn('price');
            $table->json('positions')->nullable();
        });
    }
};
