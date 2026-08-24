<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class CleanActivityLogs extends Command
{
    protected $signature = 'logs:clean';

    protected $description = 'Delete activity log entries older than each module\'s configured retention period (Settings > Logs)';

    public function handle(): int
    {
        $retentionByModule = json_decode(Setting::get('log_retention_by_module', '{}'), true) ?: [];

        // log_name is nullable on the activity_log table, and a module not
        // present in the setting at all (e.g. added after the setting was
        // last saved) falls back to 90 days rather than being silently kept
        // forever or deleted immediately.
        $modules = Activity::query()->distinct()->pluck('log_name');

        $totalDeleted = 0;

        foreach ($modules as $module) {
            $days = (int) ($retentionByModule[$module ?? ''] ?? 90);

            if ($days <= 0) {
                $this->info("Skipping \"{$module}\" — retention set to never auto-delete.");

                continue;
            }

            $cutoff = now()->subDays($days);
            $deleted = Activity::where('log_name', $module)->where('created_at', '<', $cutoff)->delete();
            $totalDeleted += $deleted;

            if ($deleted > 0) {
                $this->info("Deleted {$deleted} \"{$module}\" log entr".($deleted === 1 ? 'y' : 'ies')." older than {$days} days.");
            }
        }

        $this->info("Done — {$totalDeleted} log entries deleted in total.");

        return self::SUCCESS;
    }
}
