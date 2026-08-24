<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CoSponsorship extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'note'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'note'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('co_sponsorship')
            ->setDescriptionForEvent(fn (string $eventName) => "Co-Sponsorship \"{$this->name}\" has been {$eventName}");
    }
}
