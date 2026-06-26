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
        $heads = ['#', 'Player Name', 'Number', 'Initials', 'Product', 'Size', 'Qty', 'Unit Price', 'Extra Cost', 'Line Total', 'Sponsor', 'Position'];
        return $heads;
    }

    public function collection(): Collection
    {
        $rows = collect();
        $this->order->load('playerRows.itemCells.product', 'playerRows.itemCells.sponsorLogo');

        foreach ($this->order->playerRows as $player) {
            foreach ($player->itemCells as $cell) {
                $rows->push([
                    $player->player_index,
                    $player->player_name,
                    $player->number,
                    $player->initials,
                    $cell->product?->name,
                    $cell->size,
                    $cell->qty,
                    $cell->unit_price,
                    $cell->extra_cost,
                    $cell->line_total,
                    $cell->sponsorLogo?->name,
                    $cell->sponsor_position,
                ]);
            }
        }

        // Totals row
        $rows->push([]);
        $rows->push(['', '', '', '', '', '', '', '', 'ORDER TOTAL', $this->order->total, '', '']);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0E1620']]],
        ];
    }
}
