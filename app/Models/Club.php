<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Club extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name', 'code', 'logo', 'email', 'phone', 'address', 'contact_person', 'notes', 'status',
        'summer_forecast_open_at', 'summer_forecast_close_at',
        'winter_forecast_open_at', 'winter_forecast_close_at',
    ];

    protected $casts = [
        'summer_forecast_open_at' => 'date',
        'summer_forecast_close_at' => 'date',
        'winter_forecast_open_at' => 'date',
        'winter_forecast_close_at' => 'date',
    ];

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot(['id', 'club_price', 'online_store_price', 'has_club_crest', 'crest_number'])->withTimestamps();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function logoStocks(): HasMany
    {
        return $this->hasMany(LogoStock::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(ClubTeam::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'code', 'logo', 'email', 'phone', 'address', 'contact_person', 'notes', 'status',
                'summer_forecast_open_at', 'summer_forecast_close_at',
                'winter_forecast_open_at', 'winter_forecast_close_at',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('club')
            ->setDescriptionForEvent(fn (string $eventName) => "Club \"{$this->name}\" has been {$eventName}");
    }
}
