<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Embellishment extends Model
{
    protected $fillable = ['name', 'cost', 'embellishment_position_id'];

    protected $casts = ['cost' => 'decimal:2'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(EmbellishmentPosition::class, 'embellishment_position_id');
    }
}
