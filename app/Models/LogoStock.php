<?php

namespace App\Models;

use App\Support\StockNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LogoStock extends Model
{
    use LogsActivity;

    protected $fillable = [
        'club_id', 'barcode', 'logo_type_id', 'logo_stock_type', 'location', 'qty',
        'logo_name', 'width', 'height', 'image', 'vector_file_link', 'notes',
        'warehouse_id', 'position',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function logoType(): BelongsTo
    {
        return $this->belongsTo(LogoType::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['club_id', 'barcode', 'logo_type_id', 'logo_stock_type', 'location', 'qty', 'logo_name', 'width', 'height', 'notes', 'warehouse_id', 'position'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('logo_stock')
            ->setDescriptionForEvent(function (string $eventName) {
                $clubName = $this->club?->name ?? "Club #{$this->club_id}";

                if ($eventName === 'updated' && array_key_exists('qty', $this->oldAttributes ?? [])) {
                    $old = $this->oldAttributes['qty'];

                    return "Logo stock \"{$this->logo_name}\" for \"{$clubName}\" changed from {$old} to {$this->qty}";
                }

                return "Logo stock \"{$this->logo_name}\" for \"{$clubName}\" has been {$eventName}";
            });
    }

    /**
     * The single write path for the scanner and bulk grid's qty edits, so
     * the clamp-at-0 logic isn't duplicated between them. Unlike
     * ProductWarehouseStock::applyQty, this never creates a row — Logos
     * Stock rows are only created via the resource's Create/Edit form or
     * the bulk grid's "+ Add Row", which need every field, not just qty.
     */
    public static function applyQty(int $id, int $qty): ?self
    {
        $logoStock = static::find($id);

        if (! $logoStock) {
            return null;
        }

        $qty = max(0, $qty);
        $previousQty = (int) $logoStock->qty;

        if ($previousQty !== $qty) {
            $logoStock->update(['qty' => $qty]);

            StockNotifier::checkLowStock($logoStock, $previousQty);
        }

        return $logoStock;
    }
}
