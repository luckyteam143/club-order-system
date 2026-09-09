<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Replace the single `manage_media` flag with granular ones so add /
     * edit / delete can be handed out independently (a club role might get
     * view + create but not delete, say).
     *
     * "Sees every club's media + can import/export" is NOT a permission —
     * it's simply a non-club user who holds view_media. A club user with
     * view_media only ever sees / touches their own club's entries.
     *
     * master_admin passes every check via Gate::before, so it isn't listed.
     */
    private const PERMISSIONS = ['view_media', 'create_media', 'edit_media', 'delete_media'];

    private const ROLES = ['sub_admin', 'master_club', 'club_sub_user'];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $roleName) {
            Role::where('name', $roleName)->first()?->givePermissionTo(self::PERMISSIONS);
        }

        Permission::where('name', 'manage_media')->where('guard_name', 'web')->delete();
    }

    public function down(): void
    {
        Permission::firstOrCreate(['name' => 'manage_media', 'guard_name' => 'web']);
        Role::where('name', 'sub_admin')->first()?->givePermissionTo('manage_media');

        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
    }
};
