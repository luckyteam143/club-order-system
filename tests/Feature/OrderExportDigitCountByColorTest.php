<?php

namespace Tests\Feature;

use App\Exports\OrderExport;
use App\Models\Club;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * The order export's "Player Number Digit Count":
 *  - one Digit/Qty table per item Number Colour (order_items.number_color);
 *  - a player's number counted once per numbered garment of that colour;
 *  - items with no colour excluded (no "Unspecified" block);
 *  - every Qty cell a live formula over the roster Number column, so
 *    editing a number in Excel re-tallies the counts.
 */
class OrderExportDigitCountByColorTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name): Product
    {
        return Product::create([
            'name' => $name, 'barcode' => 'BC-'.uniqid(), 'default_sku' => 'SK-'.uniqid(),
            'status' => 'Active', 'retail_price' => 10, 'qty' => 0,
        ]);
    }

    private function order(Club $club): Order
    {
        return Order::create([
            'club_id' => $club->id, 'type' => 'individual', 'status' => 'draft',
            'order_kind' => 'standard', 'total' => 0,
        ]);
    }

    private function club(): Club
    {
        return Club::create(['name' => 'C', 'code' => 'C-'.uniqid(), 'email' => uniqid().'@e.test', 'status' => 'active']);
    }

    private function sheetFor(Order $order): Spreadsheet
    {
        $name = 'digit-'.$order->id.'-'.uniqid().'.xlsx';
        Excel::store(new OrderExport($order), $name, 'local');
        $ss = IOFactory::load(Storage::disk('local')->path($name));
        Storage::disk('local')->delete($name);

        return $ss;
    }

    /** @return array<string, array<string, int|string>>  colour label ('' when unlabelled) => digit/'Total' => value */
    private function digitCounts(Spreadsheet $ss): array
    {
        $sheet = $ss->getActiveSheet();
        Calculation::getInstance($ss)->clearCalculationCache();

        $result = [];
        $inSection = false;
        $label = '';

        foreach (range(1, $sheet->getHighestRow()) as $r) {
            $a = trim((string) $sheet->getCell("A{$r}")->getValue());

            if ($a === 'PLAYER NUMBER DIGIT COUNT') {
                $inSection = true;

                continue;
            }
            if (! $inSection || $a === '' || $a === 'Digit') {
                continue;
            }
            if ($a === 'No player numbers on this order.') {
                $result['__none__'] = [];

                break;
            }
            if (ctype_digit($a) || $a === 'Total') {
                $result[$label][$a] = (int) $sheet->getCell("B{$r}")->getCalculatedValue();

                continue;
            }
            $label = $a; // colour sub-header starts a new block
        }

        return $result;
    }

    /** @param array<string, int> $nonZero  e.g. ['0' => 1, '1' => 1] */
    private function block(array $nonZero): array
    {
        $b = [];
        for ($d = 0; $d <= 9; $d++) {
            $b[(string) $d] = $nonZero[(string) $d] ?? 0;
        }
        $b['Total'] = array_sum($nonZero);

        return $b;
    }

    public function test_digit_count_is_split_per_number_colour(): void
    {
        $club = $this->club();
        $order = $this->order($club);
        $navy = $order->orderItems()->create(['product_id' => $this->product('Navy')->id, 'unit_price' => 10, 'sort_order' => 0, 'number_color' => 'Navy']);
        $white = $order->orderItems()->create(['product_id' => $this->product('White')->id, 'unit_price' => 10, 'sort_order' => 1, 'number_color' => 'White']);

        $p1 = $order->playerRows()->create(['player_index' => 0, 'player_name' => 'A', 'number' => '10', 'initials' => 'A', 'section' => 'player']);
        $p1->itemCells()->create(['order_item_id' => $navy->id, 'size' => 'M', 'qty' => 1]);
        $p2 = $order->playerRows()->create(['player_index' => 1, 'player_name' => 'B', 'number' => '23', 'initials' => 'B', 'section' => 'player']);
        $p2->itemCells()->create(['order_item_id' => $white->id, 'size' => 'L', 'qty' => 1]);

        $counts = $this->digitCounts($this->sheetFor($order));

        $this->assertSame($this->block(['0' => 1, '1' => 1]), $counts['Navy']);
        $this->assertSame($this->block(['2' => 1, '3' => 1]), $counts['White']);
    }

    public function test_two_items_of_the_same_colour_count_the_number_once_per_item(): void
    {
        $club = $this->club();
        $order = $this->order($club);
        $a = $order->orderItems()->create(['product_id' => $this->product('Navy A')->id, 'unit_price' => 10, 'sort_order' => 0, 'number_color' => 'Navy']);
        $b = $order->orderItems()->create(['product_id' => $this->product('Navy B')->id, 'unit_price' => 10, 'sort_order' => 1, 'number_color' => 'Navy']);

        $p = $order->playerRows()->create(['player_index' => 0, 'player_name' => 'A', 'number' => '7', 'initials' => 'A', 'section' => 'player']);
        $p->itemCells()->create(['order_item_id' => $a->id, 'size' => 'M', 'qty' => 1]);
        $p->itemCells()->create(['order_item_id' => $b->id, 'size' => 'L', 'qty' => 1]);

        // Single colour -> no sub-header. "7" on two navy garments -> 2.
        $this->assertSame($this->block(['7' => 2]), $this->digitCounts($this->sheetFor($order))['']);
    }

    public function test_items_without_a_number_colour_are_left_out_entirely(): void
    {
        $club = $this->club();
        $order = $this->order($club);
        $navy = $order->orderItems()->create(['product_id' => $this->product('Navy')->id, 'unit_price' => 10, 'sort_order' => 0, 'number_color' => 'Navy']);
        $plain = $order->orderItems()->create(['product_id' => $this->product('Plain')->id, 'unit_price' => 10, 'sort_order' => 1]);

        $p = $order->playerRows()->create(['player_index' => 0, 'player_name' => 'A', 'number' => '5', 'initials' => 'A', 'section' => 'player']);
        $p->itemCells()->create(['order_item_id' => $navy->id, 'size' => 'M', 'qty' => 1]);
        $p->itemCells()->create(['order_item_id' => $plain->id, 'size' => 'OS', 'qty' => 1]);

        $counts = $this->digitCounts($this->sheetFor($order));

        $this->assertSame(['' => $this->block(['5' => 1])], $counts); // only one block, no "Unspecified"
    }

    public function test_digit_count_stays_a_single_table_when_no_colours_are_set(): void
    {
        $club = $this->club();
        $order = $this->order($club);
        $item = $order->orderItems()->create(['product_id' => $this->product('Plain')->id, 'unit_price' => 10, 'sort_order' => 0]);
        $p = $order->playerRows()->create(['player_index' => 0, 'player_name' => 'A', 'number' => '11', 'initials' => 'A', 'section' => 'player']);
        $p->itemCells()->create(['order_item_id' => $item->id, 'size' => 'M', 'qty' => 1]);

        $this->assertSame($this->block(['1' => 2]), $this->digitCounts($this->sheetFor($order))['']);
    }

    public function test_qty_cells_recompute_when_a_roster_number_is_edited(): void
    {
        $club = $this->club();
        $order = $this->order($club);
        $item = $order->orderItems()->create(['product_id' => $this->product('Navy')->id, 'unit_price' => 10, 'sort_order' => 0, 'number_color' => 'Navy']);
        $p = $order->playerRows()->create(['player_index' => 0, 'player_name' => 'A', 'number' => '10', 'initials' => 'A', 'section' => 'player']);
        $p->itemCells()->create(['order_item_id' => $item->id, 'size' => 'M', 'qty' => 1]);

        $ss = $this->sheetFor($order);
        $sheet = $ss->getActiveSheet();

        // Starts at digits of "10".
        $this->assertSame($this->block(['0' => 1, '1' => 1]), $this->digitCounts($ss)['']);

        // Find the roster Number cell (col C, first data row under the "Number" header) and edit it.
        $numberRow = null;
        foreach (range(1, $sheet->getHighestRow()) as $r) {
            if (trim((string) $sheet->getCell("C{$r}")->getValue()) === 'Number') {
                $numberRow = $r + 1;
                break;
            }
        }
        $this->assertNotNull($numberRow);
        $this->assertSame('10', (string) $sheet->getCell("C{$numberRow}")->getValue());

        $sheet->setCellValue("C{$numberRow}", '99');

        // The formulas now reflect "99".
        $this->assertSame($this->block(['9' => 2]), $this->digitCounts($ss)['']);
    }
}
