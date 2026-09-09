<?php

namespace Tests\Feature;

use App\Imports\PackageEmbellishmentsImport;
use App\Models\Club;
use App\Models\Embellishment;
use App\Models\EmbellishmentPosition;
use App\Models\Package;
use App\Models\PackageProductEmbellishment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * The Edit Package screen's "Import Embellishments" — updates an existing
 * assignment by ID, adds new ones matched by item barcode + embellishment
 * name, is non-destructive, and skips exact duplicates.
 */
class PackageEmbellishmentsImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeClub(): Club
    {
        return Club::create(['name' => 'Test Club', 'code' => 'TC-'.uniqid(), 'email' => uniqid().'@example.test', 'status' => 'active']);
    }

    private function makeProduct(string $name, string $barcode): Product
    {
        return Product::create([
            'name' => $name, 'barcode' => $barcode, 'default_sku' => 'SKU-'.uniqid(),
            'status' => 'Active', 'retail_price' => 10, 'qty' => 0,
        ]);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function import(Package $package, array $rows): PackageEmbellishmentsImport
    {
        $import = new PackageEmbellishmentsImport($package);
        $import->collection(new Collection($rows));

        return $import;
    }

    public function test_imports_updates_and_additions_without_deleting_unlisted_rows(): void
    {
        $package = Package::create(['name' => 'Kit', 'club_id' => $this->makeClub()->id, 'price' => 0, 'status' => 'active']);

        $jersey = $this->makeProduct('Jersey', 'BC-JERSEY');
        $shorts = $this->makeProduct('Shorts', 'BC-SHORTS');

        $jerseyItem = $package->packageProducts()->create(['product_id' => $jersey->id, 'sort_order' => 1, 'qty' => 1]);
        $shortsItem = $package->packageProducts()->create(['product_id' => $shorts->id, 'sort_order' => 2, 'qty' => 1]);

        $back = EmbellishmentPosition::create(['name' => 'Middle Back']);
        $front = EmbellishmentPosition::create(['name' => 'Left Chest']);
        $bigNumber = Embellishment::create(['name' => 'Large Number', 'cost' => 5, 'embellishment_position_id' => $back->id]);
        $badge = Embellishment::create(['name' => 'Team Badge', 'cost' => 3, 'embellishment_position_id' => $front->id]);

        // Existing assignment on the jersey, plus one on the shorts that the
        // import never mentions (must survive).
        $existing = PackageProductEmbellishment::create([
            'package_product_id' => $jerseyItem->id, 'embellishment_id' => $bigNumber->id,
            'embellishment_position_id' => $back->id, 'override_price' => 0,
        ]);
        $untouched = PackageProductEmbellishment::create([
            'package_product_id' => $shortsItem->id, 'embellishment_id' => $bigNumber->id,
            'embellishment_position_id' => $back->id, 'override_price' => 1,
        ]);

        $result = $this->import($package, [
            // update by ID: new position + price
            ['id' => $existing->id, 'item_barcode' => 'BC-JERSEY', 'item_name' => '', 'embellishment' => 'large number', 'position' => 'Left Chest', 'override_price' => '2.50'],
            // new assignment on the jersey, matched by barcode + name
            ['id' => '', 'item_barcode' => 'BC-JERSEY', 'item_name' => '', 'embellishment' => 'Team Badge', 'position' => 'Left Chest', 'override_price' => ''],
            // exact duplicate of $untouched -> skipped, not counted
            ['id' => '', 'item_barcode' => 'BC-SHORTS', 'item_name' => '', 'embellishment' => 'Large Number', 'position' => 'Middle Back', 'override_price' => ''],
        ]);

        $this->assertSame(2, $result->imported);
        $this->assertSame([], $result->errors);

        $existing->refresh();
        $this->assertSame($front->id, $existing->embellishment_position_id);
        $this->assertSame('2.50', (string) $existing->override_price);

        $this->assertDatabaseHas('package_product_embellishments', [
            'package_product_id' => $jerseyItem->id, 'embellishment_id' => $badge->id, 'embellishment_position_id' => $front->id,
        ]);
        // nothing deleted; duplicate not re-created
        $this->assertSame(3, PackageProductEmbellishment::count());
        $this->assertDatabaseHas('package_product_embellishments', ['id' => $untouched->id]);
    }

    public function test_reports_unresolvable_rows_and_rows_outside_the_package(): void
    {
        $package = Package::create(['name' => 'Kit', 'club_id' => $this->makeClub()->id, 'price' => 0, 'status' => 'active']);
        $inPackage = $this->makeProduct('Jersey', 'BC-JERSEY');
        $notInPackage = $this->makeProduct('Cap', 'BC-CAP');
        $package->packageProducts()->create(['product_id' => $inPackage->id, 'sort_order' => 1, 'qty' => 1]);

        EmbellishmentPosition::create(['name' => 'Middle Back']);
        Embellishment::create(['name' => 'Large Number', 'cost' => 5]);

        $result = $this->import($package, [
            ['id' => '', 'item_barcode' => 'BC-JERSEY', 'embellishment' => 'Unknown Mark', 'position' => '', 'override_price' => ''],
            ['id' => '', 'item_barcode' => 'BC-CAP', 'embellishment' => 'Large Number', 'position' => '', 'override_price' => ''],
            ['id' => '', 'item_barcode' => 'BC-JERSEY', 'embellishment' => 'Large Number', 'position' => 'Nowhere', 'override_price' => ''],
        ]);

        $this->assertSame(0, $result->imported);
        $this->assertCount(3, $result->errors);
        $this->assertSame(0, PackageProductEmbellishment::count());
    }
}
