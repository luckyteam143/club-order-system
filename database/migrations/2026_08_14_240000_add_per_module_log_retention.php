<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replaces the single global log_retention_days setting with one
     * retention period per module (0 = never auto-delete that module's
     * logs) — stored as one JSON blob rather than a row per module so
     * adding a new module later doesn't need another migration.
     */
    public function up(): void
    {
        $modules = [
            'product', 'warehouse', 'club', 'package', 'stock', 'user', 'role',
            'permission', 'attribute', 'co_sponsorship', 'embellishment',
            'embellishment_position', 'sponsor_logo', 'order',
        ];

        // Carry the old global value forward as every module's starting
        // point, so behavior doesn't silently change for existing installs.
        $previousDefault = (int) (DB::table('settings')->where('key', 'log_retention_days')->value('value') ?? 90);

        $perModule = array_fill_keys($modules, $previousDefault);

        DB::table('settings')->updateOrInsert(
            ['key' => 'log_retention_by_module'],
            ['value' => json_encode($perModule), 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'log_retention_by_module')->delete();
    }
};
