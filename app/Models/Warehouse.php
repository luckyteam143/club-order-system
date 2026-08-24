<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Warehouse extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'code', 'address', 'status'];

    public function stocks(): HasMany
    {
        return $this->hasMany(ProductWarehouseStock::class);
    }

    public function logoStocks(): HasMany
    {
        return $this->hasMany(LogoStock::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'address', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('warehouse')
            ->setDescriptionForEvent(fn (string $eventName) => "Warehouse \"{$this->name}\" has been {$eventName}");
    }
}
