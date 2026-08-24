<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Package extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'club_id', 'price', 'status', 'is_copy'];

    protected $casts = ['price' => 'decimal:2', 'is_copy' => 'boolean'];

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->using(PackageProduct::class)
            ->withPivot(['qty', 'per_item_price'])
            ->withTimestamps();
    }

    public function packageProducts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PackageProduct::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'club_id', 'price', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('package')
            ->setDescriptionForEvent(fn (string $eventName) => "Package \"{$this->name}\" has been {$eventName}");
    }
}
