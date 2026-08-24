<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logo_stocks', function (Blueprint $table) {
            $table->enum('logo_stock_type', ['logo', 'numbers'])->default('logo')->after('logo_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('logo_stocks', function (Blueprint $table) {
            $table->dropColumn('logo_stock_type');
        });
    }
};
