<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = ['manage_club_teams'];

    /**
     * master_club/club_sub_user don't need this permission at all — their
     * access comes from ClubTeamResource::canAccess()'s isClub() carve-out
     * (same idiom as manage_sponsor_logos), not from a granted permission.
     */
    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::where('name', 'master_admin')->first()?->givePermissionTo(self::PERMISSIONS);
        Role::where('name', 'sub_admin')->first()?->givePermissionTo(self::PERMISSIONS);
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
    }
};
