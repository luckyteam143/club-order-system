<?php

namespace Tests\Feature;

use App\Filament\Resources\ClubCatalogResource;
use App\Filament\Resources\ClubCatalogResource\Pages\ListClubCatalog;
use App\Models\Club;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The club-panel-only, read-only Club Catalog: a club user sees exactly the
 * products assigned to their own club, and nobody else can reach it.
 */
class ClubCatalogResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeClub(string $name): Club
    {
        return Club::create([
            'name' => $name,
            'code' => strtoupper($name),
            'email' => strtolower($name).'@example.test',
            'status' => 'active',
        ]);
    }

    private function clubUser(Club $club): User
    {
        $user = User::factory()->create(['club_id' => $club->id]);
        $user->assignRole('master_club');

        return $user;
    }

    private function makeProduct(string $name): Product
    {
        return Product::create([
            'name' => $name,
            'barcode' => 'BC-'.uniqid(),
            'default_sku' => 'SKU-'.uniqid(),
            'status' => 'Active',
            'retail_price' => 10,
            'qty' => 0,
        ]);
    }

    public function test_club_user_sees_only_their_own_clubs_assigned_products(): void
    {
        $mine = $this->makeClub('Mine');
        $other = $this->makeClub('Other');

        $assigned = $this->makeProduct('Assigned Kit');
        $unassigned = $this->makeProduct('Unassigned Kit');
        $othersItem = $this->makeProduct('Other Club Kit');

        $mine->products()->attach($assigned, [
            'club_price' => 42, 'online_store_price' => 55, 'has_club_crest' => true, 'crest_number' => 2,
        ]);
        $other->products()->attach($othersItem, ['club_price' => 10]);

        $this->actingAs($this->clubUser($mine));
        Filament::setCurrentPanel(Filament::getPanel('clubs'));

        Livewire::test(ListClubCatalog::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$assigned])
            ->assertCanNotSeeTableRecords([$unassigned, $othersItem]);
    }

    public function test_an_admin_cannot_access_the_club_catalog(): void
    {
        $admin = User::factory()->create(['club_id' => null]);
        $admin->assignRole('master_admin');

        $this->actingAs($admin);

        $this->assertFalse(ClubCatalogResource::canAccess());
    }
}
