<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permission slugs mirror the existing Filament Resources' canAccess()
     * gates 1:1, plus a couple of order-specific ones for the club
     * main-user/sub-user split.
     */
    private const PERMISSIONS = [
        'manage_products',
        'manage_stock',
        'manage_orders',
        'create_orders',
        'view_club_orders',
        'manage_packages',
        'manage_clubs',
        'manage_attributes',
        'manage_co_sponsorships',
        'manage_embellishment_positions',
        'manage_embellishments',
        'manage_sponsor_logos',
        'manage_warehouses',
        'manage_users',
        'manage_roles',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $masterAdmin = Role::firstOrCreate(['name' => 'master_admin', 'guard_name' => 'web']);
        $subAdmin = Role::firstOrCreate(['name' => 'sub_admin', 'guard_name' => 'web']);
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
        $masterClub = Role::firstOrCreate(['name' => 'master_club', 'guard_name' => 'web']);
        $clubSubUser = Role::firstOrCreate(['name' => 'club_sub_user', 'guard_name' => 'web']);

        // master_admin also gets a Gate::before bypass (see AppServiceProvider)
        // so it always has access regardless of what's synced here — this
        // sync is mainly so the Roles admin screen visibly shows it holding
        // every permission rather than looking empty.
        $masterAdmin->syncPermissions(self::PERMISSIONS);

        $subAdmin->syncPermissions(array_values(array_diff(self::PERMISSIONS, ['manage_users', 'manage_roles'])));

        // employee starts with no permissions — an admin grants what's needed via the Roles screen.
        $masterClub->syncPermissions(['create_orders', 'view_club_orders']);
        $clubSubUser->syncPermissions(['create_orders']);

        // Migrate every existing user's old `role` enum value onto the new role system.
        $roleMap = [
            'admin' => 'master_admin',
            'subadmin' => 'sub_admin',
            'club' => 'master_club',
        ];

        DB::table('users')->select('id', 'role')->orderBy('id')->get()->each(function ($row) use ($roleMap) {
            $newRoleName = $roleMap[$row->role] ?? null;

            if (! $newRoleName) {
                return;
            }

            $user = User::find($row->id);
            $user?->assignRole($newRoleName);
        });
    }

    public function down(): void
    {
        Role::whereIn('name', ['master_admin', 'sub_admin', 'employee', 'master_club', 'club_sub_user'])
            ->where('guard_name', 'web')
            ->delete();

        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();
    }
};
