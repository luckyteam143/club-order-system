<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class EmbellishmentPosition extends Model
{
    use LogsActivity;

    protected $fillable = ['name'];

    public function embellishments(): HasMany
    {
        return $this->hasMany(Embellishment::class);
    }

    public function sponsorLogos(): HasMany
    {
        return $this->hasMany(SponsorLogo::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('embellishment_position')
            ->setDescriptionForEvent(fn (string $eventName) => "Embellishment Position \"{$this->name}\" has been {$eventName}");
    }
}
