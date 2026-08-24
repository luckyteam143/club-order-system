<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Embellishment extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'cost', 'embellishment_position_id'];

    protected $casts = ['cost' => 'decimal:2'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(EmbellishmentPosition::class, 'embellishment_position_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'cost', 'embellishment_position_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('embellishment')
            ->setDescriptionForEvent(fn (string $eventName) => "Embellishment \"{$this->name}\" has been {$eventName}");
    }
}
