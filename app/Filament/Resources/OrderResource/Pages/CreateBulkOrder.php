<?php

namespace App\Filament\Resources\OrderResource\Pages;

/**
 * The same Create Order page/form (a Bulk Order is just a regular Order
 * with order_kind='bulk', per PersistsOrderGrid) — this only exists to give
 * "Bulk Order / Forecast" its own direct navigation link, pre-selecting
 * Bulk instead of making a user open the regular Create Order form and
 * remember to switch the Order Kind dropdown themselves. Type
 * (package/individual/club_items) is left for the user to pick as normal —
 * it still governs where the item columns come from even for a bulk order.
 */
class CreateBulkOrder extends CreateOrder
{
    protected function fillForm(): void
    {
        parent::fillForm();

        $this->data['order_kind'] = 'bulk';
    }

    public function getTitle(): string
    {
        return 'Create Bulk Order / Forecast';
    }
}
