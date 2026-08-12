<x-filament-panels::page>
    @include('filament.forms.stock-grid', [
        'warehouses' => $warehousesForGrid,
        'initial' => json_decode($gridState, true) ?: [],
    ])
</x-filament-panels::page>
