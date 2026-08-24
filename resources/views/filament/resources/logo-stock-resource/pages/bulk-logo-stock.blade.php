<x-filament-panels::page>
    @include('filament.forms.logo-stock-grid', [
        'clubs' => $clubs,
        'logoTypes' => $logoTypes,
        'warehouses' => $warehouses,
        'initialSearch' => $initialSearch,
    ])
</x-filament-panels::page>
