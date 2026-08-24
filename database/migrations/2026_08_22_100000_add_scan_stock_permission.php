<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Separate from `manage_stock` on purpose — an employee scanning stock
     * on a handheld device on the warehouse floor shouldn't need the full
     * Stock resource (bulk edit, import/export, manual create/delete) that
     * `manage_stock` also grants. Admins can grant just this permission to
     * an `employee` role for scanner-only access.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'scan_stock', 'guard_name' => 'web']);

        Role::where('name', 'master_admin')->first()?->givePermissionTo('scan_stock');
        Role::where('name', 'sub_admin')->first()?->givePermissionTo('scan_stock');
    }

    public function down(): void
    {
        Permission::where('name', 'scan_stock')->where('guard_name', 'web')->delete();
    }
};
