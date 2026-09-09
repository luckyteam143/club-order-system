<?php

namespace App\Filament\Concerns;

use Filament\Support\Enums\MaxWidth;

/**
 * Widens a page's main section to the full viewport instead of Filament's
 * default 7xl. Applied to every resource's list page so tables get the
 * same breathing room the Orders listing has always had — columns stay on
 * screen instead of forcing horizontal scroll on wide monitors.
 */
trait HasFullWidthContent
{
    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::Full;
    }
}
