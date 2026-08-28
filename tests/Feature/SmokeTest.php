<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Baseline "the stack is wired up" checks: migrations build on the MySQL
 * test schema, the DB round-trips, and the two Filament panels answer.
 * These exist mainly so `php artisan test` has something real to run and
 * so the test DB is exercised end to end.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_and_seed_data_are_present(): void
    {
        // 2026_08_21_100001_seed_initial_roles_and_permissions runs during
        // migrate, so a freshly refreshed schema already has roles.
        $this->assertGreaterThan(0, DB::table('roles')->count());
        $this->assertGreaterThan(0, DB::table('permissions')->count());
    }

    public function test_user_persists_to_the_test_database(): void
    {
        $user = User::factory()->create(['email' => 'smoke@example.test']);

        $this->assertDatabaseHas('users', ['email' => 'smoke@example.test']);
        $this->assertSame('b2c_macronstore_test', DB::connection()->getDatabaseName());
        $this->assertNotNull($user->fresh());
    }

    public function test_root_redirects_to_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_admin_panel_requires_authentication(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}
