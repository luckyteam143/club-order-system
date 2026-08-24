<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Brochure extends Model
{
    use LogsActivity;

    protected $fillable = ['club_id', 'link', 'created_date', 'approved_date', 'notes'];

    protected $casts = [
        'created_date' => 'date',
        'approved_date' => 'date',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['club_id', 'link', 'created_date', 'approved_date', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('brochure')
            ->setDescriptionForEvent(function (string $eventName) {
                $clubName = $this->club?->name ?? "Club #{$this->club_id}";

                return "Brochure for \"{$clubName}\" has been {$eventName}";
            });
    }
}
