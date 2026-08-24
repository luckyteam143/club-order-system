<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Mirrors manage_stock / scan_stock: manage_logo_stock covers the full
     * Logos Stock resource (listing, create/edit, bulk grid), scan_logo_stock
     * is separate so an employee can be granted scanner-only access without
     * the full resource. manage_logo_types gates the small Logo Type lookup
     * table, same split as manage_embellishment_positions vs manage_sponsor_logos.
     */
    private const PERMISSIONS = [
        'manage_logo_types',
        'manage_logo_stock',
        'scan_logo_stock',
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
