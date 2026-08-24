<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_kind')->default('standard')->after('type');
        });

        // The old overloaded type='bulk' value is being split into its own
        // order_kind field — classify existing rows before repointing type
        // to a real Type value (individual: bulk orders always let the user
        // freely pick any product column, exactly like Individual orders).
        DB::table('orders')
            ->where('type', 'bulk')
            ->where(function ($query) {
                $query->where('status', 'forecast_submitted')->orWhereNotNull('forecast_season');
            })
            ->update(['order_kind' => 'forecast']);

        DB::table('orders')
            ->where('type', 'bulk')
            ->where('order_kind', 'standard')
            ->update(['order_kind' => 'bulk']);

        DB::table('orders')->where('type', 'bulk')->update(['type' => 'individual']);
    }

    public function down(): void
    {
        DB::table('orders')->where('order_kind', '!=', 'standard')->update(['type' => 'bulk']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_kind');
        });
    }
};
