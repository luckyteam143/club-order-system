<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Concerns\PersistsOrderGrid;
use App\Models\Club;
use App\Models\Embellishment;
use App\Models\EmbellishmentPosition;
use App\Models\Order;
use App\Models\OrderItemEmbellishment;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A package's predefined embellishment override price must survive onto an
 * order placed by someone who can't edit prices (a club user) — before the
 * fix the override was dropped and the order/export fell back to the
 * embellishment's catalog cost.
 */
class PackageEmbellishmentOverridePriceTest extends TestCase
{
    use RefreshDatabase;

    private function persister(): object
    {
        return new class
        {
            use PersistsOrderGrid;

            public function run(Order $order, array $state): void
            {
                $this->persistGridState($order, $state);
            }
        };
    }

    /**
     * @param  array{override_price: float|null}  $packageEmbellishment
     */
    private function scaffold(array $packageEmbellishment): array
    {
        $club = Club::create(['name' => 'C', 'code' => 'C-'.uniqid(), 'email' => uniqid().'@e.test', 'status' => 'active']);

        $product = Product::create([
            'name' => 'Jersey', 'barcode' => 'BC-'.uniqid(), 'default_sku' => 'SK-'.uniqid(),
            'status' => 'Active', 'retail_price' => 20, 'qty' => 0,
        ]);

        $position = EmbellishmentPosition::create(['name' => 'Middle Back']);
        $embellishment = Embellishment::create(['name' => 'Large Number', 'cost' => 3.00, 'embellishment_position_id' => $position->id]);

        $package = Package::create(['name' => 'Kit', 'club_id' => $club->id, 'price' => 0, 'status' => 'active']);
        $packageProduct = $package->packageProducts()->create(['product_id' => $product->id, 'sort_order' => 1, 'qty' => 1]);
        $packageProduct->embellishments()->create([
            'embellishment_id' => $embellishment->id,
            'embellishment_position_id' => $position->id,
            'override_price' => $packageEmbellishment['override_price'],
        ]);

        $order = Order::create([
            'club_id' => $club->id, 'type' => 'package', 'package_id' => $package->id,
            'status' => 'draft', 'order_kind' => 'standard', 'total' => 0,
        ]);

        $state = [
            'columns' => [[
                'key' => 'c1', 'id' => null, 'product_id' => $product->id, 'notes' => '',
                'has_club_crest' => false, 'crest_number' => 1, 'is_goalie_item' => false, 'is_player_item' => true,
            ]],
            'rows' => [],
            'sponsors' => [],
            'embellishments' => [[
                'key' => 'em1', 'id' => null, 'item_key' => 'c1',
                'embellishment_id' => $embellishment->id, 'embellishment_position_id' => $position->id,
                // What the (spoofable) client submits — must never be trusted for a non-pricing user.
                'override_price' => 99.99,
            ]],
        ];

        return compact('club', 'order', 'state');
    }

    public function test_club_user_order_keeps_the_packages_embellishment_override(): void
    {
        ['order' => $order, 'club' => $club, 'state' => $state] = $this->scaffold(['override_price' => 12.50]);

        $user = User::factory()->create(['club_id' => $club->id]);
        $user->assignRole('master_club'); // no manage_order_pricing
        $this->actingAs($user);

        $this->persister()->run($order, $state);

        $row = OrderItemEmbellishment::firstWhere('order_item_id', $order->orderItems()->value('id'));
        $this->assertSame('12.50', (string) $row->override_price, 'package override should win');
        $this->assertSame('12.50', (string) $row->price, 'effective price should be the package override, not catalog cost');
    }

    public function test_no_package_override_falls_back_to_catalog_cost(): void
    {
        ['order' => $order, 'club' => $club, 'state' => $state] = $this->scaffold(['override_price' => null]);

        $user = User::factory()->create(['club_id' => $club->id]);
        $user->assignRole('master_club');
        $this->actingAs($user);

        $this->persister()->run($order, $state);

        $row = OrderItemEmbellishment::firstWhere('order_item_id', $order->orderItems()->value('id'));
        $this->assertNull($row->override_price);
        $this->assertSame('3.00', (string) $row->price);
    }

    public function test_a_pricing_editor_still_applies_their_submitted_override(): void
    {
        ['order' => $order, 'state' => $state] = $this->scaffold(['override_price' => 12.50]);

        $admin = User::factory()->create(['club_id' => null]);
        $admin->assignRole('master_admin'); // passes every gate, incl. manage_order_pricing
        $this->actingAs($admin);

        $this->persister()->run($order, $state);

        $row = OrderItemEmbellishment::firstWhere('order_item_id', $order->orderItems()->value('id'));
        $this->assertSame('99.99', (string) $row->override_price);
        $this->assertSame('99.99', (string) $row->price);
    }
}
