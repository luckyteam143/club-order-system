<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Club;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Orders listing shows a "View Brochure" row action — for admins and
 * club users alike — only when the order has a brochure link.
 */
class OrderBrochureLinkActionTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Order $withLink;

    private Order $withoutLink;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::create([
            'name' => 'Brochure Club', 'code' => 'BRO', 'email' => 'bro@example.test', 'status' => 'active',
        ]);

        $this->withLink = Order::create([
            'club_id' => $this->club->id, 'type' => 'individual', 'status' => 'submitted',
            'brochure_link' => 'https://example.com/brochure.pdf',
        ]);
        $this->withoutLink = Order::create([
            'club_id' => $this->club->id, 'type' => 'individual', 'status' => 'submitted',
        ]);
    }

    public function test_admin_sees_the_action_only_when_a_link_is_set(): void
    {
        $admin = User::factory()->create(['club_id' => null]);
        $admin->assignRole('master_admin');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('viewBrochure', $this->withLink)
            ->assertTableActionHidden('viewBrochure', $this->withoutLink);
    }

    public function test_club_user_also_sees_the_action_with_the_link_url(): void
    {
        $user = User::factory()->create(['club_id' => $this->club->id]);
        $user->assignRole('master_club');

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('clubs'));

        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('viewBrochure', $this->withLink)
            ->assertTableActionHidden('viewBrochure', $this->withoutLink)
            ->assertTableActionHasUrl('viewBrochure', 'https://example.com/brochure.pdf', $this->withLink);
    }
}
