<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Who can manage the shared Media library. Picking an existing media
     * file from another form doesn't need this — only opening the Media
     * module itself (upload / rename / delete / import / export) does.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'manage_media', 'guard_name' => 'web']);

        Role::where('name', 'master_admin')->first()?->givePermissionTo('manage_media');
        Role::where('name', 'sub_admin')->first()?->givePermissionTo('manage_media');
    }

    public function down(): void
    {
        Permission::where('name', 'manage_media')->where('guard_name', 'web')->delete();
    }
};
