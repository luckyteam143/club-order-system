<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Attribute extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'sku', 'position'];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'position'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('attribute')
            ->setDescriptionForEvent(fn (string $eventName) => "Attribute \"{$this->name}\" has been {$eventName}");
    }
}
