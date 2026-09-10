<?php

namespace App\Exports;

use App\Models\Attribute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPlayerRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One sheet, laid out to match the club's reference packing-slip template
 * (order-6-Vaughan-SC.xlsx) cell for cell: fixed column widths, a bold
 * navy "ORDER DETAILS" / black "FOR OFFICE USE" title bar with the two
 * field groups running side by side, plain (undecorated) titles for the
 * Roster / Item-Size-Summary / Sponsor sections, black-filled white column
 * headers, and thin borders throughout. The sheet is built entirely in
 * the AfterSheet event for full control over that layout — collection()
 * is unused.
 *
 * Every roster size cell gets an in-cell dropdown scoped to that item's own
 * product sizes (ordered small to largest), pre-filled with the size the
 * player already has selected.
 */
class OrderExport implements FromCollection, WithEvents, WithPreCalculateFormulas, WithTitle
{
    private const NAVY = '0E1620';

    private const BLACK = '000000';

    private const WHITE = 'FFFFFF';

    public function __construct(private Order $order) {}

    public function title(): string
    {
        return 'Order #'.$this->order->id;
    }

    public function collection(): Collection
    {
        return collect();
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->build($event->sheet->getDelegate());
            },
        ];
    }

    private function build(Worksheet $sheet): void
    {
        $this->order->loadMissing([
            'club', 'package',
            'orderItems.product.attributes',
            'orderItems.cells',
            'orderItems.sponsors.sponsorLogo', 'orderItems.sponsors.position',
            'orderItems.embellishments.embellishment', 'orderItems.embellishments.position',
            'playerRows.itemCells',
        ]);

        $items = $this->order->orderItems;

        $sheet->getColumnDimension('A')->setWidth(28.1);
        $sheet->getColumnDimension('B')->setWidth(18.36);
        $sheet->getColumnDimension('C')->setWidth(8.34);
        $sheet->getColumnDimension('D')->setWidth(12.66);
        $sheet->getColumnDimension('E')->setWidth(15.99);
        $sheet->getColumnDimension('F')->setWidth(15.58);
        $sheet->getColumnDimension('G')->setWidth(11.4);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_LETTER)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(1);
        $sheet->setPrintGridlines(false);

        $row = 1;

        // ── Club title ───────────────────────────────────────────────
        $sheet->setCellValue("A{$row}", 'Club');
        $sheet->setCellValue("B{$row}", $this->order->club?->name);
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
        ]);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $row += 2;

        // ── ORDER DETAILS / FOR OFFICE USE title bar ────────────────
        $barRow = $row;
        $this->fillRange($sheet, "A{$barRow}:G{$barRow}", self::NAVY, self::WHITE, bold: true);
        $sheet->setCellValue("A{$barRow}", 'ORDER DETAILS');

        $sheet->setCellValue("D{$barRow}", 'FOR OFFICE USE');
        $this->fillRange($sheet, "D{$barRow}", self::BLACK, self::WHITE, bold: false);
        $row++;

        $leftFields = [
            ['Order #', (string) $this->order->id],
            ['Type', ucfirst($this->order->type ?? '')],
            ['Team / PO #', $this->order->team_po],
            ['Coach / Manager', $this->order->coach_manager],
            ['Shipping Address', $this->order->shipping_address],
            ['Phone', $this->order->phone],
            ['Email', $this->order->email],
        ];

        $rightFields = [
            ['Order Date', $this->order->order_date?->format('M j, Y') ?? ''],
            ['B2B Number', $this->order->b2b_number],
            ['QB Invoice #', $this->order->qb_invoice],
            ['Brochure Link', $this->order->brochure_link],
            ['Order Notes', $this->order->notes],
            ['Required By Date', $this->order->required_by_date?->format('M j, Y') ?? ''],
        ];

        for ($i = 0; $i < count($leftFields); $i++) {
            $r = $row + $i;
            [$label, $value] = $leftFields[$i];
            $wrap = $label === 'Shipping Address';

            $sheet->setCellValue("A{$r}", $label);
            $sheet->setCellValue("B{$r}", $value);
            $this->styleField($sheet, "A{$r}", "B{$r}", $wrap);
            if ($wrap) {
                $sheet->getRowDimension($r)->setRowHeight(38.05);
            }
        }

        for ($i = 0; $i < count($rightFields); $i++) {
            $r = $row + $i;
            [$label, $value] = $rightFields[$i];
            $wrap = $label === 'Order Notes';
            $bold = $i === 0;

            $sheet->setCellValue("D{$r}", $label);
            $sheet->setCellValue("E{$r}", $value);
            $this->styleField($sheet, "D{$r}", "E{$r}", $wrap, $bold);
        }

        // Keep the office-use column's border grid flush with the taller
        // left column even past its own last field.
        for ($i = count($rightFields); $i < count($leftFields); $i++) {
            $r = $row + $i;
            $this->styleField($sheet, "D{$r}", "E{$r}", false);
        }

        $row += count($leftFields) + 1;

        // ── Roster(s) ─────────────────────────────────────────────────
        // A package item can be flagged Goalkeeper Item / Player Item
        // independently — when the order actually carries any
        // goalkeeper-flagged item, the roster splits into two separate
        // tables (own columns, own player rows, by
        // order_player_rows.section) mirroring the Order grid's on-screen
        // split. Every other order keeps the single combined "ROSTER"
        // table exactly as before this existed. Either way, the Item/Size
        // Summary and every section below stay combined across both
        // tables — buildItemSizeSummary() sums a COUNTIF per roster
        // location an item appears in (one location = the exact same
        // formula as before the split).
        $hasGoalieItems = $items->contains(fn (OrderItem $item) => (bool) $item->is_goalie_item);
        $itemLocations = [];

        if ($hasGoalieItems) {
            $goalieItems = $items->filter(fn (OrderItem $item) => (bool) $item->is_goalie_item)->values();
            $playerItems = $items->filter(fn (OrderItem $item) => (bool) $item->is_player_item)->values();
            $goalieRows = $this->order->playerRows->filter(fn ($r) => $r->section === 'goalie')->values();
            $playerSectionRows = $this->order->playerRows->filter(fn ($r) => $r->section !== 'goalie')->values();

            $goalieRoster = $this->buildRoster($sheet, $row, $goalieItems, $goalieRows, 'GOALKEEPER ROSTER');
            $row = $goalieRoster['nextRow'] + 1;

            $playerRoster = $this->buildRoster($sheet, $row, $playerItems, $playerSectionRows, 'PLAYER ROSTER');
            $row = $playerRoster['nextRow'] + 1;

            foreach ($goalieItems as $item) {
                $itemLocations[$item->id][] = $this->rosterLocation($goalieRoster, $item->id);
            }
            foreach ($playerItems as $item) {
                $itemLocations[$item->id][] = $this->rosterLocation($playerRoster, $item->id);
            }
        } else {
            $roster = $this->buildRoster($sheet, $row, $items, $this->order->playerRows, 'ROSTER');
            $row = $roster['nextRow'] + 1;

            foreach ($items as $item) {
                $itemLocations[$item->id] = [$this->rosterLocation($roster, $item->id)];
            }
        }

        // ── Item / Size Summary ─────────────────────────────────────
        $row = $this->buildItemSizeSummary($sheet, $row, $items, $itemLocations);
        $row++;

        // ── Sponsor Logos & Embellishments ───────────────────────────
        $row = $this->buildSponsors($sheet, $row, $items);
        $row++;

        // Logos to physically produce = one per garment of the item that
        // carries them, i.e. the item's ordered qty across the roster.
        $qtyByItem = $items->mapWithKeys(fn (OrderItem $item) => [$item->id => (int) $item->cells->sum('qty')]);

        // ── Club Crest Logos Required ────────────────────────────────
        $row = $this->buildCrestSummary($sheet, $row, $items, $qtyByItem);
        $row++;

        // ── Sponsor Logos Required ───────────────────────────────────
        $row = $this->buildSponsorSummary($sheet, $row, $items, $qtyByItem);
        $row++;

        // ── Player Number Digit Count ────────────────────────────────
        $this->buildDigitCount($sheet, $row, $items, $itemLocations);
    }

    /**
     * Builds one roster table for the given items (as columns) and player
     * rows (as data rows) — called once for a plain order (all items, all
     * rows, titled "ROSTER"), or twice for a Package order with goalkeeper
     * items (once per section, each with its own items/rows/title). See
     * rosterLocation() and buildItemSizeSummary() for how a summary row
     * combines results across however many tables an item appears in.
     *
     * @param  Collection<int, OrderItem>  $items
     * @param  Collection<int, OrderPlayerRow>  $rows
     * @return array{nextRow: int, itemCols: array<int, string>, headerRow: int, dataRowStart: int, dataRowEnd: int, formulasUsable: bool}
     */
    private function buildRoster(Worksheet $sheet, int $row, Collection $items, Collection $rows, string $title = 'ROSTER'): array
    {
        $isBulk = in_array($this->order->order_kind, ['bulk', 'forecast'], true);
        $sheet->setCellValue("A{$row}", $title);
        $this->plainTitle($sheet, "A{$row}");
        $row++;

        $headerRow = $row;
        $lastCol = $this->colLetter(3 + $items->count() + 1); // Name, Initials, Number + items + Notes
        $sheet->setCellValue("A{$headerRow}", 'Player Name');
        $sheet->setCellValue("B{$headerRow}", 'Initials');
        $sheet->setCellValue("C{$headerRow}", 'Number');

        $itemCols = [];
        $col = 4; // D
        foreach ($items as $item) {
            $letter = $this->colLetter($col);
            $itemCols[$item->id] = $letter;
            $sheet->setCellValue("{$letter}{$headerRow}", $item->product?->name ?? 'Item');
            $col++;
        }
        $notesCol = $this->colLetter($col);
        $sheet->setCellValue("{$notesCol}{$headerRow}", 'Notes');

        $this->fillRange($sheet, "A{$headerRow}:{$lastCol}{$headerRow}", self::BLACK, self::WHITE, bold: false, wrap: true, borderColor: self::WHITE);
        $sheet->getRowDimension($headerRow)->setRowHeight(41.75);
        $row++;

        // Each item's own dropdown, ordered smallest-to-largest per the
        // product's attribute position — matches the size order shown.
        // Bulk/forecast orders don't have a real per-player size (their
        // single implicit row holds a qty per size instead), so no
        // dropdown makes sense there — those roster rows are left blank
        // below and the Item/Size Summary totals carry the real quantities.
        $validations = [];
        if (! $isBulk) {
            foreach ($items as $item) {
                $sizes = $item->product?->attributes->pluck('name')->all() ?? [];
                if ($sizes === []) {
                    continue;
                }

                $validation = new DataValidation;
                $validation->setType(DataValidation::TYPE_LIST);
                $validation->setErrorStyle(DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                $validation->setShowInputMessage(false);
                $validation->setShowErrorMessage(true);
                $validation->setShowDropDown(true); // PhpSpreadsheet's writer inverts this for the OOXML attribute; true = arrow shown
                $validation->setFormula1('"'.implode(',', $sizes).'"');
                $validations[$item->id] = $validation;
            }
        }

        $dataRowStart = $row;
        $playerCount = 0;

        foreach ($rows as $player) {
            $cellsByItem = $isBulk ? collect() : $player->itemCells->keyBy('order_item_id');
            $playerCount++;

            $sheet->setCellValue("A{$row}", $isBulk ? '' : $player->player_name);
            $sheet->setCellValue("B{$row}", $isBulk ? '' : $player->initials);
            $sheet->setCellValue("C{$row}", $isBulk ? '' : $player->number);
            $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_GENERAL);
            $this->bordered($sheet, "B{$row}", Alignment::HORIZONTAL_CENTER);
            $this->bordered($sheet, "C{$row}", Alignment::HORIZONTAL_CENTER);

            foreach ($items as $item) {
                $letter = $itemCols[$item->id];
                $size = $cellsByItem->get($item->id)?->size ?? '';
                $sheet->setCellValue("{$letter}{$row}", $size);
                $this->bordered($sheet, "{$letter}{$row}", Alignment::HORIZONTAL_CENTER, bold: true);

                if (isset($validations[$item->id])) {
                    $sheet->getCell("{$letter}{$row}")->setDataValidation(clone $validations[$item->id]);
                }
            }

            $sheet->setCellValue("{$notesCol}{$row}", $isBulk ? '' : $player->notes);
            $this->bordered($sheet, "{$notesCol}{$row}", Alignment::HORIZONTAL_GENERAL);

            $row++;
        }

        return [
            'nextRow' => $row,
            'itemCols' => $itemCols,
            'headerRow' => $headerRow,
            'dataRowStart' => $dataRowStart,
            'dataRowEnd' => $dataRowStart + $playerCount - 1,
            'formulasUsable' => ! $isBulk && $playerCount > 0,
        ];
    }

    /**
     * A single item's position(s) in the roster table(s) it appears in —
     * one entry for a plain order or a single-section item, two for an
     * item flagged both Goalkeeper Item and Player Item (it then shares
     * one order_item but has a column in both roster tables).
     *
     * @param  array{itemCols: array<int, string>, headerRow: int, dataRowStart: int, dataRowEnd: int, formulasUsable: bool}  $roster
     * @return array{col: string, headerRow: int, dataRowStart: int, dataRowEnd: int, formulasUsable: bool}|null
     */
    private function rosterLocation(array $roster, int $itemId): ?array
    {
        $col = $roster['itemCols'][$itemId] ?? null;

        if (! $col) {
            return null;
        }

        return [
            'col' => $col,
            'headerRow' => $roster['headerRow'],
            'dataRowStart' => $roster['dataRowStart'],
            'dataRowEnd' => $roster['dataRowEnd'],
            'formulasUsable' => $roster['formulasUsable'],
        ];
    }

    /**
     * One line per product, combined across every roster table it occupies
     * — an item split goalkeeper + player, or the same product added as two
     * order items, gets a single row whose cells sum a COUNTIF per column;
     * a product with its usual single column gets the exact same
     * single-COUNTIF formula this produced before, so an order with no
     * repeats and no split is byte-for-byte unchanged.
     *
     * @param  Collection<int, OrderItem>  $items
     * @param  array<int, list<array{col: string, headerRow: int, dataRowStart: int, dataRowEnd: int, formulasUsable: bool}|null>>  $itemLocations  order item id => its roster location(s)
     */
    private function buildItemSizeSummary(Worksheet $sheet, int $row, Collection $items, array $itemLocations): int
    {
        $sheet->setCellValue("A{$row}", 'ITEM / SIZE SUMMARY');
        $this->plainTitle($sheet, "A{$row}");
        $row++;

        $allCells = $this->order->playerRows->flatMap(fn ($player) => $player->itemCells);

        // Every size any item on the order actually offers (its product's
        // own Attribute list) — not just the ones someone has picked in the
        // roster so far — so a size nobody ordered still gets its own
        // (blank/zero) column instead of silently disappearing.
        $sizeOrder = Attribute::orderBy('position')->orderBy('name')->pluck('name')->flip();
        $sizes = $items->flatMap(fn (OrderItem $item) => $item->product?->attributes->pluck('name') ?? collect())
            ->filter()->unique()
            ->sort(fn ($a, $b) => [$sizeOrder[$a] ?? PHP_INT_MAX, $a] <=> [$sizeOrder[$b] ?? PHP_INT_MAX, $b])
            ->values();

        $headerRow = $row;
        $sheet->setCellValue("A{$headerRow}", 'Item');
        $col = 2; // B
        foreach ($sizes as $size) {
            $sheet->setCellValue($this->colLetter($col)."{$headerRow}", $size);
            $col++;
        }
        $totalCol = $this->colLetter($col);
        $sheet->setCellValue("{$totalCol}{$headerRow}", 'Total');

        $lastCol = $totalCol;
        $this->fillRange($sheet, "A{$headerRow}:{$lastCol}{$headerRow}", self::BLACK, self::WHITE, bold: false, center: true);
        $row++;

        // When the roster grid actually has size cells to point at, drive
        // every per-item-per-size count with a COUNTIF formula instead of a
        // baked-in number, so fixing a mis-picked size in the roster
        // updates these totals automatically instead of leaving them
        // stale. Bulk/forecast orders have no such roster to COUNTIF
        // against, so their per-cell counts stay static — but the row and
        // column Totals are SUM()s of this summary table's own cells
        // either way, so those stay formula-based unconditionally: SUM
        // doesn't care whether the cells it's adding are themselves
        // formulas or plain numbers.
        $useTotalFormulas = $sizes->isNotEmpty();
        $firstSizeCol = $this->colLetter(2);
        $lastSizeCol = $this->colLetter(1 + $sizes->count());

        $columnTotals = array_fill_keys($sizes->all(), 0);
        $grandTotal = 0;
        $firstItemRow = $row;

        // The same product can be on an order as more than one order item
        // (a package that lists it twice, a goalkeeper + player split, a
        // hand-built order). Collapse those onto one summary line whose
        // size cells COUNTIF across every roster column the product
        // occupies, so a repeated item shows a single combined total and
        // the totals stay formula-driven. product_id keys the grouping;
        // the rare item with no product falls back to its own id so it is
        // never merged with another.
        $itemGroups = $items->groupBy(fn (OrderItem $item) => $item->product_id ?? "item-{$item->id}");

        foreach ($itemGroups as $groupItems) {
            $groupItems = $groupItems->values();
            $groupItemIds = $groupItems->pluck('id')->all();
            $representative = $groupItems->first();

            $itemCells = $allCells->whereIn('order_item_id', $groupItemIds);
            $itemTotal = 0;

            // Every roster column occupied by any item in this group, in
            // first-seen order. A single-item group with one location
            // reduces to exactly the one-COUNTIF formula built before this
            // grouping existed, so an order with no repeats is unchanged.
            $locations = collect($groupItemIds)
                ->flatMap(fn ($id) => $itemLocations[$id] ?? [])
                ->filter()
                ->values()
                ->all();

            // Only formula-driven when every table these items appear in
            // can support it (bulk / forecast orders have no roster to
            // COUNTIF against) — otherwise the group's cells fall back to a
            // static sum of its order items' quantities.
            $useFormulas = $sizes->isNotEmpty() && $locations !== []
                && collect($locations)->every(fn ($l) => $l['formulasUsable']);

            $firstLocation = $locations[0] ?? null;

            // Point at the roster's own header cell rather than baking in
            // the name twice, so editing it there keeps this in sync.
            $sheet->setCellValue("A{$row}", $firstLocation ? "={$firstLocation['col']}{$firstLocation['headerRow']}" : ($representative->product?->name ?? 'Item'));
            $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_LEFT, wrap: true);

            $col = 2;
            foreach ($sizes as $size) {
                $cell = $this->colLetter($col)."{$row}";

                if ($useFormulas) {
                    $escaped = str_replace('"', '""', $size);
                    $countIfs = array_map(
                        fn ($l) => "COUNTIF({$l['col']}\${$l['dataRowStart']}:{$l['col']}\${$l['dataRowEnd']},\"{$escaped}\")",
                        $locations,
                    );
                    $sheet->setCellValue($cell, '='.implode('+', $countIfs));
                } else {
                    $qty = (int) $itemCells->where('size', $size)->sum('qty');
                    $sheet->setCellValue($cell, $qty ?: '');
                    $columnTotals[$size] += $qty;
                    $itemTotal += $qty;
                }

                $this->bordered($sheet, $cell, Alignment::HORIZONTAL_CENTER);
                $col++;
            }

            $grandTotal += $itemTotal;

            if ($useTotalFormulas) {
                $sheet->setCellValue("{$totalCol}{$row}", "=SUM({$firstSizeCol}{$row}:{$lastSizeCol}{$row})");
            } else {
                $sheet->setCellValue("{$totalCol}{$row}", $itemTotal);
            }
            $this->bordered($sheet, "{$totalCol}{$row}", Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $lastItemRow = $row - 1;

        $sheet->setCellValue("A{$row}", 'Total');
        $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_CENTER);
        $col = 2;
        foreach ($sizes as $size) {
            $cell = $this->colLetter($col)."{$row}";
            if ($useTotalFormulas) {
                $letter = $this->colLetter($col);
                $sheet->setCellValue($cell, "=SUM({$letter}{$firstItemRow}:{$letter}{$lastItemRow})");
            } else {
                $sheet->setCellValue($cell, $columnTotals[$size]);
            }
            $this->bordered($sheet, $cell, Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        if ($useTotalFormulas) {
            $sheet->setCellValue("{$totalCol}{$row}", "=SUM({$totalCol}{$firstItemRow}:{$totalCol}{$lastItemRow})");
        } else {
            $sheet->setCellValue("{$totalCol}{$row}", $grandTotal);
        }
        $this->bordered($sheet, "{$totalCol}{$row}", Alignment::HORIZONTAL_CENTER);

        return $row + 1;
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @return int the next free row
     */
    private function buildSponsors(Worksheet $sheet, int $row, Collection $items): int
    {
        $sheet->setCellValue("A{$row}", 'SPONSOR LOGOS & EMBELLISHMENTS');
        $this->plainTitle($sheet, "A{$row}");
        $row++;

        $headerRow = $row;
        foreach (['A' => 'Item', 'B' => 'Type', 'C' => 'Name', 'D' => 'Position', 'E' => 'Price'] as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $this->fillRange($sheet, "A{$headerRow}:E{$headerRow}", self::BLACK, self::WHITE, bold: false);
        $row++;

        $hasAny = false;

        foreach ($items as $item) {
            foreach ($item->sponsors as $sponsor) {
                $this->sponsorRow($sheet, $row, $item->product?->name ?? 'Item', 'Sponsor Logo',
                    $sponsor->sponsorLogo?->name ?? '—', $sponsor->position?->name ?? '—', (float) $sponsor->price);
                $row++;
                $hasAny = true;
            }

            foreach ($item->embellishments as $embellishment) {
                $this->sponsorRow($sheet, $row, $item->product?->name ?? 'Item', 'Embellishment',
                    $embellishment->embellishment?->name ?? '—', $embellishment->position?->name ?? '—', (float) $embellishment->price);
                $row++;
                $hasAny = true;
            }
        }

        if (! $hasAny) {
            $sheet->setCellValue("A{$row}", 'None on this order.');
            $row++;
        }

        return $row;
    }

    /**
     * How many club-crest logos to produce, grouped by crest number — the
     * club may use a different crest artwork on the jersey vs. the shorts.
     * One logo per garment of each crest-carrying item (its roster qty).
     *
     * @param  Collection<int, OrderItem>  $items
     * @param  Collection<int, int>  $qtyByItem  order item id => ordered qty
     */
    private function buildCrestSummary(Worksheet $sheet, int $row, Collection $items, Collection $qtyByItem): int
    {
        $sheet->setCellValue("A{$row}", 'CLUB CREST LOGOS REQUIRED');
        $this->plainTitle($sheet, "A{$row}");
        $row++;

        $headerRow = $row;
        $sheet->setCellValue("A{$headerRow}", 'Crest #');
        $sheet->setCellValue("B{$headerRow}", 'Qty');
        $this->fillRange($sheet, "A{$headerRow}:B{$headerRow}", self::BLACK, self::WHITE, bold: false);
        $row++;

        $tally = [];
        foreach ($items as $item) {
            if (! $item->has_club_crest) {
                continue;
            }
            $number = (int) ($item->crest_number ?: 1);
            $tally[$number] = ($tally[$number] ?? 0) + (int) ($qtyByItem[$item->id] ?? 0);
        }

        if ($tally === []) {
            $sheet->setCellValue("A{$row}", 'No club crests on this order.');

            return $row + 1;
        }

        ksort($tally);
        $total = 0;

        foreach ($tally as $number => $qty) {
            $sheet->setCellValue("A{$row}", 'Crest '.$number);
            $sheet->setCellValue("B{$row}", $qty);
            $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_LEFT);
            $this->bordered($sheet, "B{$row}", Alignment::HORIZONTAL_CENTER);
            $total += $qty;
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Total');
        $sheet->setCellValue("B{$row}", $total);
        $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_CENTER, bold: true);
        $this->bordered($sheet, "B{$row}", Alignment::HORIZONTAL_CENTER, bold: true);

        return $row + 1;
    }

    /**
     * How many of each sponsor logo to produce — one per garment of every
     * item carrying it (its roster qty), summed across items / positions.
     *
     * @param  Collection<int, OrderItem>  $items
     * @param  Collection<int, int>  $qtyByItem  order item id => ordered qty
     */
    private function buildSponsorSummary(Worksheet $sheet, int $row, Collection $items, Collection $qtyByItem): int
    {
        $sheet->setCellValue("A{$row}", 'SPONSOR LOGOS REQUIRED');
        $this->plainTitle($sheet, "A{$row}");
        $row++;

        $headerRow = $row;
        foreach (['A' => 'Sponsor Logo', 'B' => 'Position', 'C' => 'Qty'] as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $this->fillRange($sheet, "A{$headerRow}:C{$headerRow}", self::BLACK, self::WHITE, bold: false);
        $row++;

        $tally = [];
        foreach ($items as $item) {
            $qty = (int) ($qtyByItem[$item->id] ?? 0);

            foreach ($item->sponsors as $sponsor) {
                $name = $sponsor->sponsorLogo?->name ?? '—';
                $position = $sponsor->position?->name ?? '—';
                $key = $name.'|'.$position;
                $tally[$key] ??= ['name' => $name, 'position' => $position, 'qty' => 0];
                $tally[$key]['qty'] += $qty;
            }
        }

        if ($tally === []) {
            $sheet->setCellValue("A{$row}", 'No sponsor logos on this order.');

            return $row + 1;
        }

        $total = 0;

        foreach ($tally as $entry) {
            $sheet->setCellValue("A{$row}", $entry['name']);
            $sheet->setCellValue("B{$row}", $entry['position']);
            $sheet->setCellValue("C{$row}", $entry['qty']);
            $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_LEFT);
            $this->bordered($sheet, "B{$row}", Alignment::HORIZONTAL_LEFT);
            $this->bordered($sheet, "C{$row}", Alignment::HORIZONTAL_CENTER);
            $total += $entry['qty'];
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Total');
        $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_CENTER, bold: true);
        $this->bordered($sheet, "B{$row}", Alignment::HORIZONTAL_LEFT);
        $sheet->setCellValue("C{$row}", $total);
        $this->bordered($sheet, "C{$row}", Alignment::HORIZONTAL_CENTER, bold: true);

        return $row + 1;
    }

    /**
     * Frequency of each digit 0–9 across roster player numbers — heat press
     * number kits are bought per digit. Every Qty cell is a live formula
     * over the roster's Number column (C), so editing a number in Excel
     * re-tallies the counts (and the Total) automatically. All ten digits
     * are always listed so a digit introduced by an edit still has a row.
     * Bulk/forecast orders have no player numbers.
     *
     * When any item defines a Number Colour (package_product.number_color,
     * snapshotted onto order_items), the count splits into one table per
     * colour: a player's number is tallied once for every numbered garment
     * they order in that colour (the formula multiplies the digit count by
     * how many of that colour's item columns the player has a size in — a
     * player with two navy items counts twice for navy). Items with no
     * colour set are left out entirely. With no colours anywhere it stays
     * a single combined table (each roster number counted once).
     *
     * @param  Collection<int, OrderItem>  $items
     * @param  array<int, list<array{col: string, headerRow: int, dataRowStart: int, dataRowEnd: int, formulasUsable: bool}|null>>  $itemLocations
     */
    private function buildDigitCount(Worksheet $sheet, int $row, Collection $items, array $itemLocations): void
    {
        $sheet->setCellValue("A{$row}", 'PLAYER NUMBER DIGIT COUNT');
        $this->plainTitle($sheet, "A{$row}");
        $row++;

        $noNumbers = fn () => $sheet->setCellValue("A{$row}", 'No player numbers on this order.');

        if (in_array($this->order->order_kind, ['bulk', 'forecast'], true)) {
            $noNumbers();

            return;
        }

        // Every usable roster location, flattened: an item id + the roster
        // row range it lives in + its own size column in that roster.
        $locations = [];
        foreach ($itemLocations as $itemId => $locs) {
            foreach (array_filter((array) $locs) as $loc) {
                if ($loc['formulasUsable'] ?? false) {
                    $locations[] = [
                        'itemId' => (int) $itemId,
                        'start' => (int) $loc['dataRowStart'],
                        'end' => (int) $loc['dataRowEnd'],
                        'col' => $loc['col'],
                    ];
                }
            }
        }

        if ($locations === []) {
            $noNumbers();

            return;
        }

        // Number lives in column C of every roster. Counts how many times
        // $digit occurs down one roster's Number column.
        $countInNumbers = static fn (int $start, int $end, string $digit): string => 'LEN($C$'.$start.':$C$'.$end.')-LEN(SUBSTITUTE($C$'.$start.':$C$'.$end.',"'.$digit.'",""))';

        // order_item id => its number colour, for items that actually set one.
        $colourByItem = [];
        foreach ($items as $item) {
            $colour = trim((string) $item->number_color);
            if ($colour !== '') {
                $colourByItem[$item->id] = $colour;
            }
        }

        // No colours anywhere — one combined table; each roster number
        // counted once, so just sum a SUMPRODUCT per distinct roster range.
        if ($colourByItem === []) {
            $ranges = [];
            foreach ($locations as $l) {
                $ranges["{$l['start']}:{$l['end']}"] = [$l['start'], $l['end']];
            }

            $this->writeDigitTableFormulas($sheet, $row, function (string $digit) use ($ranges, $countInNumbers): string {
                return implode('+', array_map(
                    fn ($r) => 'SUMPRODUCT('.$countInNumbers($r[0], $r[1], $digit).')',
                    $ranges,
                ));
            });

            return;
        }

        // Per colour: group each colour's item size-columns by the roster
        // range they sit in.
        $byColour = [];
        foreach ($locations as $l) {
            $colour = $colourByItem[$l['itemId']] ?? null;
            if ($colour === null) {
                continue;
            }

            $key = mb_strtolower($colour);
            $rangeKey = "{$l['start']}:{$l['end']}";
            $byColour[$key]['label'] ??= $colour;
            $byColour[$key]['ranges'][$rangeKey]['start'] = $l['start'];
            $byColour[$key]['ranges'][$rangeKey]['end'] = $l['end'];
            $byColour[$key]['ranges'][$rangeKey]['cols'][] = $l['col'];
        }

        if ($byColour === []) {
            $noNumbers();

            return;
        }

        ksort($byColour); // colours A–Z

        $showLabels = count($byColour) > 1;

        foreach ($byColour as $data) {
            if ($showLabels) {
                $sheet->setCellValue("A{$row}", $data['label']);
                $this->fillRange($sheet, "A{$row}:B{$row}", self::NAVY, self::WHITE, bold: true);
                $row++;
            }

            $row = $this->writeDigitTableFormulas($sheet, $row, function (string $digit) use ($data, $countInNumbers): string {
                $parts = [];
                foreach ($data['ranges'] as $r) {
                    $presence = array_map(
                        fn ($col) => '($'.$col.'$'.$r['start'].':$'.$col.'$'.$r['end'].'<>"")',
                        $r['cols'],
                    );
                    // digit count × (how many of this colour's garments the player took)
                    $parts[] = 'SUMPRODUCT(('.$countInNumbers($r['start'], $r['end'], $digit).')*('.implode('+', $presence).'))';
                }

                return implode('+', $parts);
            }) + 2;
        }
    }

    /**
     * Writes a Digit / Qty table: header, one row per digit 0–9 whose Qty
     * is `=<formulaFor($digit)>`, then a Total row that SUMs them. Returns
     * the Total row.
     *
     * @param  callable(string): string  $formulaFor  digit ("0".."9") => formula body (no leading "=")
     */
    private function writeDigitTableFormulas(Worksheet $sheet, int $row, callable $formulaFor): int
    {
        $headerRow = $row;
        $sheet->setCellValue("A{$headerRow}", 'Digit');
        $sheet->setCellValue("B{$headerRow}", 'Qty');
        $this->fillRange($sheet, "A{$headerRow}:B{$headerRow}", self::BLACK, self::WHITE, bold: false);
        $row++;

        $firstDataRow = $row;

        for ($d = 0; $d <= 9; $d++) {
            $sheet->setCellValue("A{$row}", (string) $d);
            $sheet->setCellValue("B{$row}", '='.$formulaFor((string) $d));
            $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_CENTER);
            $this->bordered($sheet, "B{$row}", Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Total');
        $sheet->setCellValue("B{$row}", "=SUM(B{$firstDataRow}:B".($row - 1).')');
        $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_CENTER, bold: true);
        $this->bordered($sheet, "B{$row}", Alignment::HORIZONTAL_CENTER, bold: true);

        return $row;
    }

    private function sponsorRow(Worksheet $sheet, int $row, string $item, string $type, string $name, string $position, float $price): void
    {
        $sheet->setCellValue("A{$row}", $item);
        $sheet->setCellValue("B{$row}", $type);
        $sheet->setCellValue("C{$row}", $name);
        $sheet->setCellValue("D{$row}", $position);
        $sheet->setCellValue("E{$row}", '$'.number_format($price, 2));

        $sheet->getStyle("A{$row}:E{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_GENERAL, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BLACK]]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(23.85);
    }

    private function plainTitle(Worksheet $sheet, string $cell): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => false],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_GENERAL],
        ]);
    }

    private function styleField(Worksheet $sheet, string $labelCell, string $valueCell, bool $wrap = false, bool $bold = false): void
    {
        $labelStyle = [
            'font' => ['bold' => $bold],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_GENERAL, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BLACK]]],
        ];
        $valueStyle = [
            'font' => ['bold' => $bold],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => $wrap],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BLACK]]],
        ];

        $sheet->getStyle($labelCell)->applyFromArray($labelStyle);
        $sheet->getStyle($valueCell)->applyFromArray($valueStyle);
    }

    private function bordered(Worksheet $sheet, string $cell, string $align, bool $bold = false, bool $wrap = false): void
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => $bold],
            'alignment' => ['horizontal' => $align, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => $wrap],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BLACK]]],
        ]);
    }

    private function fillRange(Worksheet $sheet, string $range, string $fillColor, string $fontColor, bool $bold, bool $wrap = false, bool $center = false, ?string $borderColor = null): void
    {
        $style = [
            'font' => ['bold' => $bold, 'color' => ['rgb' => $fontColor]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
            'alignment' => [
                'horizontal' => $center ? Alignment::HORIZONTAL_CENTER : Alignment::HORIZONTAL_GENERAL,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => $wrap,
            ],
        ];

        if ($borderColor) {
            $style['borders'] = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $borderColor]]];
        }

        $sheet->getStyle($range)->applyFromArray($style);
    }

    private function colLetter(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }
}
