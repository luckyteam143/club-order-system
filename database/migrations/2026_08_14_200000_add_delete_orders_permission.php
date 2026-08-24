<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * There was previously no way to delete an order once submitted (no
     * per-row delete action on the list, and OrderPolicy had no delete()
     * method at all — only master_admin's Gate::before bypass let anything
     * through). A dedicated permission, rather than hardcoding isAdmin(),
     * so this can be handed to another role later without a code change.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'delete_orders', 'guard_name' => 'web']);

        Role::where('name', 'master_admin')->first()?->givePermissionTo('delete_orders');
    }

    public function down(): void
    {
        Permission::where('name', 'delete_orders')->where('guard_name', 'web')->delete();
    }
};
