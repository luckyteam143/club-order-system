<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmbellishmentPosition extends Model
{
    protected $fillable = ['name'];

    public function embellishments(): HasMany
    {
        return $this->hasMany(Embellishment::class);
    }

    public function sponsorLogos(): HasMany
    {
        return $this->hasMany(SponsorLogo::class);
    }
}
