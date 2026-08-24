<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SponsorLogo extends Model
{
    use LogsActivity;

    protected $fillable = ['club_id', 'name', 'file', 'vector_file', 'conversion_requested', 'embellishment_position_id', 'price'];

    protected $casts = ['price' => 'decimal:2', 'conversion_requested' => 'boolean'];

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function position(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EmbellishmentPosition::class, 'embellishment_position_id');
    }

    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['club_id', 'name', 'file', 'vector_file', 'conversion_requested', 'embellishment_position_id', 'price'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('sponsor_logo')
            ->setDescriptionForEvent(fn (string $eventName) => "Sponsor Logo \"{$this->name}\" has been {$eventName}");
    }
}
