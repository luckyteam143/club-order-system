<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Club;
use App\Models\CoSponsorship;
use App\Models\Embellishment;
use App\Models\EmbellishmentPosition;
use App\Models\Package;
use App\Models\Product;
use App\Models\SponsorLogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Seeds the catalog straight from database/seeders/data/club-orders-system.xlsx,
 * so the data stays a faithful copy of the workbook instead of a hand-transcribed
 * snapshot. Re-run any time the workbook changes.
 */
class WorkbookSeeder extends Seeder
{
    private array $clubIdMap = [];
    private array $productIdMap = [];
    private array $positionIdMap = [];
    private array $packageIdMap = [];

    public function run(): void
    {
        $path = database_path('seeders/data/club-orders-system.xlsx');
        $spreadsheet = IOFactory::load($path);

        $this->seedAttributes($spreadsheet);
        $this->seedCoSponsorships($spreadsheet);
        $this->seedEmbellishmentPositions($spreadsheet);
        $this->seedEmbellishments($spreadsheet);
        $this->seedClubs($spreadsheet);
        $this->seedProducts($spreadsheet);
        $this->seedSponsorLogos($spreadsheet);
        $this->seedPackages($spreadsheet);
        $this->seedPackageItems($spreadsheet);
    }

    /** @return array<int, array<int, mixed>> rows after the header, 0-indexed columns */
    private function rows(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet, string $sheetName): array
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        $all = $sheet->toArray(null, true, true, false);
        array_shift($all); // drop header row

        return array_values(array_filter($all, fn ($row) => trim((string) ($row[0] ?? '')) !== ''));
    }

    private function str(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }

    private function num(mixed $value, float $default = 0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (float) $value;
    }

    private function seedAttributes(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'ATTRIBUTES') as $row) {
            Attribute::updateOrCreate(
                ['sku' => $this->str($row[2])],
                ['name' => $this->str($row[1])],
            );
        }
    }

    private function seedCoSponsorships(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'CO-SPONSORSHIP') as $row) {
            CoSponsorship::updateOrCreate(
                ['name' => $this->str($row[1])],
                ['note' => $this->str($row[2])],
            );
        }
    }

    private function seedEmbellishmentPositions(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'Embellishment Positions') as $row) {
            $position = EmbellishmentPosition::updateOrCreate(
                ['name' => $this->str($row[1])],
            );
            $this->positionIdMap[(int) $row[0]] = $position->id;
        }
    }

    private function seedEmbellishments(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'Embelishments') as $row) {
            $positionName = $this->str($row[3]);
            $positionId = null;
            if ($positionName) {
                $positionId = EmbellishmentPosition::firstOrCreate(['name' => $positionName])->id;
            }

            Embellishment::updateOrCreate(
                ['name' => $this->str($row[1]), 'embellishment_position_id' => $positionId],
                ['cost' => $this->num($row[2])],
            );
        }
    }

    private function seedClubs(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'CLUBS') as $row) {
            $club = Club::updateOrCreate(
                ['email' => $this->str($row[2]) ?? strtolower(str_replace(' ', '', (string) $row[1])).'@example.com'],
                [
                    'name'    => $this->str($row[1]),
                    'phone'   => $this->str($row[3]),
                    'address' => $this->str($row[4]),
                    'status'  => strtolower($this->str($row[5]) ?? 'active'),
                ],
            );
            $this->clubIdMap[(int) $row[0]] = $club->id;
        }
    }

    private function seedProducts(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        $rows = $this->rows($spreadsheet, 'PRODUCTS');

        // Parents first (blank Parent SKU), then children, so attribute
        // matching always has the sizing lookup available.
        usort($rows, fn ($a, $b) => (trim((string) ($a[4] ?? '')) !== '') <=> (trim((string) ($b[4] ?? '')) !== ''));

        foreach ($rows as $row) {
            $coSponsorshipId = null;
            if ($name = $this->str($row[13])) {
                $coSponsorshipId = CoSponsorship::firstOrCreate(['name' => $name])->id;
            }

            $product = Product::updateOrCreate(
                ['barcode' => $this->str($row[2])],
                [
                    'status'                => $this->str($row[1]) ?? 'Active',
                    'default_sku'           => $this->str($row[3]),
                    'parent_sku'            => $this->str($row[4]),
                    'size'                  => $this->str($row[5]),
                    'name'                  => $this->str($row[6]) ?? 'Unnamed product',
                    'description'           => $this->str($row[7]),
                    'qty'                   => (int) $this->num($row[8]),
                    'on_backorder'          => strtolower((string) ($row[9] ?? 'No')) === 'yes',
                    'backorder_date'        => $this->str($row[10]),
                    'retail_price'          => $this->num($row[11]),
                    'co_sponsorship_id'     => $coSponsorshipId,
                    'image_link'            => $this->str($row[15]),
                    'gallery_links'         => $this->str($row[16]),
                    'year'                  => $this->str($row[17]),
                    'available_until_year'  => $this->str($row[18]),
                    'total_look'            => $this->str($row[19]),
                    'weight'                => $this->num($row[20], 0),
                    'color1_code'           => $this->str($row[21]),
                    'color1_label'          => $this->str($row[22]),
                    'color2_code'           => $this->str($row[23]),
                    'color2_label'          => $this->str($row[24]),
                ],
            );

            $this->productIdMap[(int) $row[0]] = $product->id;

            $sizeNames = array_filter(array_map('trim', explode(',', (string) ($row[12] ?? ''))));
            if ($sizeNames) {
                $attributeIds = Attribute::whereIn('name', $sizeNames)->pluck('id', 'name');
                $product->attributes()->sync($attributeIds->values()->all());
            }
        }
    }

    private function seedSponsorLogos(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'SPONSOR LOGOS') as $row) {
            $clubId = $this->clubIdMap[(int) $row[1]] ?? null;
            if (! $clubId) {
                continue;
            }

            SponsorLogo::updateOrCreate(
                ['club_id' => $clubId, 'name' => $this->str($row[2])],
                [
                    'file'                      => $this->str($row[3]),
                    'embellishment_position_id' => $this->positionIdMap[(int) $row[4]] ?? null,
                    'price'                     => $this->num($row[5]),
                ],
            );
        }
    }

    private function seedPackages(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'PACKAGES') as $row) {
            $clubId = $this->clubIdMap[(int) $row[2]] ?? null;
            if (! $clubId) {
                continue;
            }

            $package = Package::updateOrCreate(
                ['club_id' => $clubId, 'name' => $this->str($row[1])],
                [
                    'price'  => $this->num($row[3]),
                    'status' => strtolower($this->str($row[4]) ?? 'active'),
                ],
            );

            $this->packageIdMap[(int) $row[0]] = $package->id;
        }
    }

    private function seedPackageItems(\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet): void
    {
        foreach ($this->rows($spreadsheet, 'PACKAGE-ITEMS') as $row) {
            $packageId = $this->packageIdMap[(int) $row[0]] ?? null;
            $productId = $this->productIdMap[(int) $row[1]] ?? null;
            if (! $packageId || ! $productId) {
                continue;
            }

            DB::table('package_product')->updateOrInsert(
                ['package_id' => $packageId, 'product_id' => $productId],
                ['qty' => 1, 'per_item_price' => $this->num($row[2]), 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }
}
