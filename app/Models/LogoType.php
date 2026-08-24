<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LogoType extends Model
{
    use LogsActivity;

    protected $fillable = ['name'];

    public function logoStocks(): HasMany
    {
        return $this->hasMany(LogoStock::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('logo_type')
            ->setDescriptionForEvent(fn (string $eventName) => "Logo Type \"{$this->name}\" has been {$eventName}");
    }
}
