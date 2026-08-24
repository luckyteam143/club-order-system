<?php

namespace App\Support;

use App\Models\Club;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Whether a club's Summer/Winter Forecast submission window is open right
 * now — a club's own open/close dates (set by an admin on the Club record)
 * take over entirely when both are set; otherwise falls back to the global
 * default dates on the Settings page. No dates configured anywhere at all
 * means the window is never open (nothing to submit into).
 */
class ForecastWindow
{
    public const SEASONS = ['summer' => 'Summer Forecast', 'winter' => 'Winter Forecast'];

    public static function label(string $season): string
    {
        return self::SEASONS[$season] ?? ucfirst($season).' Forecast';
    }

    public static function openDate(Club $club, string $season): ?Carbon
    {
        $clubDate = $club->{"{$season}_forecast_open_at"};

        if ($clubDate && $club->{"{$season}_forecast_close_at"}) {
            return $clubDate;
        }

        $default = Setting::get("{$season}_forecast_open");

        return filled($default) ? Carbon::parse($default) : null;
    }

    public static function closeDate(Club $club, string $season): ?Carbon
    {
        $clubDate = $club->{"{$season}_forecast_close_at"};

        if ($clubDate && $club->{"{$season}_forecast_open_at"}) {
            return $clubDate;
        }

        $default = Setting::get("{$season}_forecast_close");

        return filled($default) ? Carbon::parse($default) : null;
    }

    public static function isOpen(Club $club, string $season): bool
    {
        $open = self::openDate($club, $season);
        $close = self::closeDate($club, $season);

        if (! $open || ! $close) {
            return false;
        }

        return Carbon::today()->between($open->copy()->startOfDay(), $close->copy()->endOfDay());
    }

    /** @return array<string, string> season => label, for whichever season(s) are open right now */
    public static function openSeasons(Club $club): array
    {
        $open = [];

        foreach (self::SEASONS as $season => $label) {
            if (self::isOpen($club, $season)) {
                $open[$season] = $label;
            }
        }

        return $open;
    }
}
