<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PickListExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private Order $order)
    {
    }

    public function title(): string
    {
        return 'Pick List';
    }

    public function headings(): array
    {
        return ['SKU', 'Name', 'Size', 'Ordered Qty', 'Picked Qty', 'Balance Qty'];
    }

    /**
     * One row per (item, size) — picking is tracked per size (sizes are
     * separate barcoded/stocked catalog products, not just descriptive
     * text on the item), so a size's own picked/balance qty is only
     * meaningful shown at that granularity, not rolled up per item.
     */
    public function collection(): Collection
    {
        $items = $this->order->orderItems()->with(['product', 'cells', 'picks'])->get();

        return $items->flatMap(fn ($item) => $item->sizeBreakdown()->map(fn ($ordered, $size) => [
            'item'    => $item,
            'size'    => $size,
            'ordered' => $ordered,
        ])->values());
    }

    public function map($row): array
    {
        $item = $row['item'];
        $size = $row['size'];

        // The item's own `product` is the parent style (no size) — every
        // size row would otherwise repeat that same parent SKU. Resolve
        // the actual size-variant catalog product (same lookup used for
        // picking's barcode/stock) so each row shows its own real SKU,
        // falling back to the parent's only if no variant exists.
        $variant = $item->resolveSizeVariant($size);

        return [
            $variant?->default_sku ?? $item->product?->default_sku,
            $item->product?->name,
            $size,
            $row['ordered'],
            $item->pickedQtyForSize($size),
            $item->balanceForSize($size),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
