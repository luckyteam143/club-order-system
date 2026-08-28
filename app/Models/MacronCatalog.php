<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class MacronCatalog extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'year', 'link', 'show_in_menu', 'sort_order'];

    protected $casts = [
        'show_in_menu' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'year', 'link', 'show_in_menu', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('macron_catalog')
            ->setDescriptionForEvent(fn (string $eventName) => "Macron catalog \"{$this->name} {$this->year}\" has been {$eventName}");
    }
}
