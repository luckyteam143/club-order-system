<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Global default Summer/Winter Forecast windows (Settings > Forecasts)
     * — used for any club that hasn't been given its own override dates.
     * No default dates are set out of the box (blank = forecast submission
     * never opens until an admin actually configures it).
     */
    public function up(): void
    {
        $rows = [
            'summer_forecast_open'  => '',
            'summer_forecast_close' => '',
            'winter_forecast_open'  => '',
            'winter_forecast_close' => '',
        ];

        foreach ($rows as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'summer_forecast_open', 'summer_forecast_close',
            'winter_forecast_open', 'winter_forecast_close',
        ])->delete();
    }
};
