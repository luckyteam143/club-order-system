<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Club users can see every price in the order grid (item unit price,
     * sponsor logo / embellishment overrides) but must not be able to
     * change any of them — only admins should. A dedicated permission (
     * rather than hardcoding isAdmin()) so it can be granted/revoked from
     * the Roles screen without a code change later.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'manage_order_pricing', 'guard_name' => 'web']);

        Role::where('name', 'master_admin')->first()?->givePermissionTo('manage_order_pricing');
        Role::where('name', 'sub_admin')->first()?->givePermissionTo('manage_order_pricing');
    }

    public function down(): void
    {
        Permission::where('name', 'manage_order_pricing')->where('guard_name', 'web')->delete();
    }
};
