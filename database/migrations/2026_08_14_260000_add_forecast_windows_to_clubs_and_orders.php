<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-club Summer/Winter Forecast submission windows — admin-only, and
     * only an override: null means "use the global default" (set on the
     * Settings page). Orders get a forecast_season marker so a submitted
     * order remembers which forecast (if any) it went in under.
     */
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->date('summer_forecast_open_at')->nullable()->after('status');
            $table->date('summer_forecast_close_at')->nullable()->after('summer_forecast_open_at');
            $table->date('winter_forecast_open_at')->nullable()->after('summer_forecast_close_at');
            $table->date('winter_forecast_close_at')->nullable()->after('winter_forecast_open_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('forecast_season')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table) {
            $table->dropColumn(['summer_forecast_open_at', 'summer_forecast_close_at', 'winter_forecast_open_at', 'winter_forecast_close_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('forecast_season');
        });
    }
};
