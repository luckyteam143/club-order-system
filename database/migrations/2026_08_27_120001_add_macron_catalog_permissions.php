<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const PERMISSIONS = [
        // Create / edit / delete catalogs in the admin module.
        'manage_macron_catalogs',
        // See the catalog links in the sidebar. Assign this to whichever
        // roles should get the "main menu" catalog shortcuts.
        'view_macron_catalogs',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::where('name', 'master_admin')->first()?->givePermissionTo(self::PERMISSIONS);
        Role::where('name', 'sub_admin')->first()?->givePermissionTo(self::PERMISSIONS);
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
    }
};
