<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class OrderExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private Order $order) {}

    public function title(): string
    {
        return 'Order #' . $this->order->id;
    }

    public function headings(): array
    {
        return ['#', 'Player Name', 'Number', 'Initials', 'Item', 'Sponsor Logo', 'Embellishment', 'Size', 'Qty', 'Unit Price (CAD)', 'Line Total (CAD)', 'Notes'];
    }

    public function collection(): Collection
    {
        $rows = collect();
        $this->order->load('playerRows.itemCells.orderItem.product', 'playerRows.itemCells.orderItem.sponsorLogo', 'playerRows.itemCells.orderItem.embellishment');

        foreach ($this->order->playerRows as $player) {
            foreach ($player->itemCells as $cell) {
                $item = $cell->orderItem;
                $rows->push([
                    $player->player_index,
                    $player->player_name,
                    $player->number,
                    $player->initials,
                    $item?->product?->name,
                    $item?->sponsorLogo?->name,
                    $item?->embellishment?->name,
                    $cell->size,
                    $cell->qty,
                    $item?->unit_price,
                    $cell->line_total,
                    $player->notes,
                ]);
            }
        }

        $rows->push([]);
        $rows->push(['', '', '', '', '', '', '', '', '', 'ORDER TOTAL', $this->order->total, '']);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
