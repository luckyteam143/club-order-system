<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'view_logs', 'guard_name' => 'web']);

        Role::where('name', 'master_admin')->first()?->givePermissionTo('view_logs');
        Role::where('name', 'sub_admin')->first()?->givePermissionTo('view_logs');
    }

    public function down(): void
    {
        Permission::where('name', 'view_logs')->where('guard_name', 'web')->delete();
    }
};
