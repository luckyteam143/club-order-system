<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, LogsActivity;

    protected $fillable = ['name', 'email', 'password', 'club_id', 'warehouse_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function club(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('master_admin');
    }

    public function isSubAdmin(): bool
    {
        return $this->hasRole('sub_admin');
    }

    /** True for either kind of club user — the main club account or one of its sub-users. */
    public function isClub(): bool
    {
        return $this->hasAnyRole(['master_club', 'club_sub_user']);
    }

    /** The main club account — full access to all of that club's orders. */
    public function isMasterClub(): bool
    {
        return $this->hasRole('master_club');
    }

    /** A club sub-user — can only create/see the orders they created themselves. */
    public function isClubSubUser(): bool
    {
        return $this->hasRole('club_sub_user');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->roles()->exists()) {
            return false;
        }

        // Club users get their own panel on clubs.macronstore.ca — kept
        // separate from the staff admin panel entirely, in both directions.
        if ($panel->getId() === 'clubs') {
            return $this->isClub();
        }

        return ! $this->isClub();
    }

    public function getActivitylogOptions(): LogOptions
    {
        // password/remember_token are deliberately excluded from every
        // logged attribute list, not just this one — never store credential
        // values (even hashed) in the audit trail.
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'club_id', 'warehouse_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('user')
            ->setDescriptionForEvent(fn (string $eventName) => "User \"{$this->name}\" has been {$eventName}");
    }
}
