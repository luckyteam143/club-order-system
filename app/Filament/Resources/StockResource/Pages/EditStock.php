<?php

namespace App\Filament\Resources\StockResource\Pages;

use App\Filament\Resources\StockResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStock extends EditRecord
{
    protected static string $resource = StockResource::class;

    protected ?int $previousProductId = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->after(fn () => $this->record->product?->recalculateStock()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->previousProductId = $this->record->product_id;

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->product?->recalculateStock();

        if ($this->previousProductId && $this->previousProductId !== $this->record->product_id) {
            Product::find($this->previousProductId)?->recalculateStock();
        }
    }
}
