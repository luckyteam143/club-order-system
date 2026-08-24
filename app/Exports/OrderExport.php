<?php

namespace App\Exports;

use App\Models\Attribute;
use App\Models\Order;
use App\Models\OrderItem;
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
            'orderItems.sponsors.sponsorLogo', 'orderItems.sponsors.position',
            'orderItems.embellishments.embellishment', 'orderItems.embellishments.position',
            'playerRows.itemCells',
        ]);

        $isStaff = auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin();
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

        if ($isStaff) {
            $sheet->setCellValue("D{$barRow}", 'FOR OFFICE USE');
            $this->fillRange($sheet, "D{$barRow}", self::BLACK, self::WHITE, bold: false);
        }
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

        $rightFields = $isStaff ? [
            ['Order Date', $this->order->order_date?->format('M j, Y') ?? ''],
            ['B2B Number', $this->order->b2b_number],
            ['QB Invoice #', $this->order->qb_invoice],
            ['Brochure Link', $this->order->brochure_link],
            ['Order Notes', $this->order->notes],
        ] : [];

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
        if ($isStaff) {
            for ($i = count($rightFields); $i < count($leftFields); $i++) {
                $r = $row + $i;
                $this->styleField($sheet, "D{$r}", "E{$r}", false);
            }
        }

        $row += count($leftFields) + 1;

        // ── Roster ────────────────────────────────────────────────────
        $roster = $this->buildRoster($sheet, $row, $items);
        $row = $roster['nextRow'] + 1;

        // ── Item / Size Summary ─────────────────────────────────────
        $row = $this->buildItemSizeSummary($sheet, $row, $items, $roster);
        $row++;

        // ── Sponsor Logos & Embellishments ───────────────────────────
        $this->buildSponsors($sheet, $row, $items);
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     * @return array{nextRow: int, itemCols: array<int, string>, headerRow: int, dataRowStart: int, dataRowEnd: int, formulasUsable: bool}
     */
    private function buildRoster(Worksheet $sheet, int $row, Collection $items): array
    {
        $isBulk = in_array($this->order->order_kind, ['bulk', 'forecast'], true);
        $sheet->setCellValue("A{$row}", 'ROSTER');
        $this->plainTitle($sheet, "A{$row}");
        $row++;

        $headerRow = $row;
        $lastCol = $this->colLetter(3 + $items->count() + 1); // Name, Number, Initials + items + Notes
        $sheet->setCellValue("A{$headerRow}", 'Player Name');
        $sheet->setCellValue("B{$headerRow}", 'Number');
        $sheet->setCellValue("C{$headerRow}", 'Initials');

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

        foreach ($this->order->playerRows as $player) {
            $cellsByItem = $isBulk ? collect() : $player->itemCells->keyBy('order_item_id');
            $playerCount++;

            $sheet->setCellValue("A{$row}", $isBulk ? '' : $player->player_name);
            $sheet->setCellValue("B{$row}", $isBulk ? '' : $player->number);
            $sheet->setCellValue("C{$row}", $isBulk ? '' : $player->initials);
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
     * @param  Collection<int, OrderItem>  $items
     * @param  array{nextRow: int, itemCols: array<int, string>, dataRowStart: int, dataRowEnd: int, formulasUsable: bool}  $roster
     */
    private function buildItemSizeSummary(Worksheet $sheet, int $row, Collection $items, array $roster): int
    {
        $sheet->setCellValue("A{$row}", 'ITEM / SIZE SUMMARY');
        $this->plainTitle($sheet, "A{$row}");
        $row++;

        $allCells = $this->order->playerRows->flatMap(fn ($player) => $player->itemCells);

        $sizeOrder = Attribute::orderBy('position')->orderBy('name')->pluck('name')->flip();
        $sizes = $allCells->pluck('size')->filter()->unique()
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
        // every count with COUNTIF/SUM formulas instead of baked-in numbers,
        // so fixing a mis-picked size in the roster updates these totals
        // automatically instead of leaving them stale.
        $useFormulas = $roster['formulasUsable'] && $sizes->isNotEmpty();
        $firstSizeCol = $this->colLetter(2);
        $lastSizeCol = $this->colLetter(1 + $sizes->count());

        $columnTotals = array_fill_keys($sizes->all(), 0);
        $grandTotal = 0;
        $firstItemRow = $row;

        foreach ($items as $item) {
            $itemCells = $allCells->where('order_item_id', $item->id);
            $itemTotal = 0;
            $itemCol = $roster['itemCols'][$item->id] ?? null;

            // Point at the roster's own header cell rather than baking in
            // the name twice, so editing it there keeps this in sync.
            $sheet->setCellValue("A{$row}", $itemCol ? "={$itemCol}{$roster['headerRow']}" : ($item->product?->name ?? 'Item'));
            $this->bordered($sheet, "A{$row}", Alignment::HORIZONTAL_LEFT, wrap: true);

            $col = 2;
            foreach ($sizes as $size) {
                $cell = $this->colLetter($col)."{$row}";

                if ($useFormulas && $itemCol) {
                    $escaped = str_replace('"', '""', $size);
                    $sheet->setCellValue($cell, "=COUNTIF({$itemCol}\${$roster['dataRowStart']}:{$itemCol}\${$roster['dataRowEnd']},\"{$escaped}\")");
                } else {
                    $qty = (int) $itemCells->where('size', $size)->sum('qty');
                    $sheet->setCellValue($cell, $qty ?: '');
                    $columnTotals[$size] += $qty;
                    $itemTotal += $qty;
                }

                $this->bordered($sheet, $cell, Alignment::HORIZONTAL_CENTER);
                $col++;
            }

            if ($useFormulas) {
                $sheet->setCellValue("{$totalCol}{$row}", "=SUM({$firstSizeCol}{$row}:{$lastSizeCol}{$row})");
            } else {
                $sheet->setCellValue("{$totalCol}{$row}", $itemTotal);
                $grandTotal += $itemTotal;
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
            if ($useFormulas) {
                $letter = $this->colLetter($col);
                $sheet->setCellValue($cell, "=SUM({$letter}{$firstItemRow}:{$letter}{$lastItemRow})");
            } else {
                $sheet->setCellValue($cell, $columnTotals[$size]);
            }
            $this->bordered($sheet, $cell, Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        if ($useFormulas) {
            $sheet->setCellValue("{$totalCol}{$row}", "=SUM({$totalCol}{$firstItemRow}:{$totalCol}{$lastItemRow})");
        } else {
            $sheet->setCellValue("{$totalCol}{$row}", $grandTotal);
        }
        $this->bordered($sheet, "{$totalCol}{$row}", Alignment::HORIZONTAL_CENTER);

        return $row + 1;
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    private function buildSponsors(Worksheet $sheet, int $row, Collection $items): void
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
        }
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
