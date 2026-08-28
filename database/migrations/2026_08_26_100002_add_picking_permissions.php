<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * `manage_picking` (admin: send orders for picking, print pick lists,
     * see picking status/highlighting) is separate from `pick_orders`
     * (employee: work the Order Picking page for orders assigned to them)
     * so an employee can be granted picking access alone, without the
     * admin-side controls — same split as `manage_stock`/`scan_stock`.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'manage_picking', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'pick_orders', 'guard_name' => 'web']);

        Role::where('name', 'master_admin')->first()?->givePermissionTo(['manage_picking', 'pick_orders']);
        Role::where('name', 'sub_admin')->first()?->givePermissionTo(['manage_picking', 'pick_orders']);
    }

    public function down(): void
    {
        Permission::where('name', 'manage_picking')->where('guard_name', 'web')->delete();
        Permission::where('name', 'pick_orders')->where('guard_name', 'web')->delete();
    }
};
