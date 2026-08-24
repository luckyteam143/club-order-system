<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ClubTeam extends Model
{
    use LogsActivity;

    protected $fillable = [
        'club_id', 'team_name', 'po_reference', 'coach_manager_name',
        'coach_manager_email', 'coach_manager_contact', 'address', 'status', 'sort_order',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'club_id', 'team_name', 'po_reference', 'coach_manager_name',
                'coach_manager_email', 'coach_manager_contact', 'address', 'status', 'sort_order',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('club_team')
            ->setDescriptionForEvent(function (string $eventName) {
                $clubName = $this->club?->name ?? "Club #{$this->club_id}";

                return "Team \"{$this->team_name}\" for \"{$clubName}\" has been {$eventName}";
            });
    }
}
