<x-filament-panels::page>
    {{-- Mostly used on a handheld device — hide the page title on small
         screens like the other scanner-style pages, same reasoning. --}}
    <style>
        @media (max-width: 640px) {
            .fi-header { display: none; }
        }

        {{-- No custom Tailwind build for custom Filament pages here — only
             Filament's own shipped compiled classes exist. These rules
             reference Filament's --{color}-{shade} CSS variables directly
             instead (always injected in <head> for every registered color),
             same pattern as the Stock/Logo Stock Scanner pages. --}}
        .opk-badge--not_picked { background-color: rgb(var(--gray-500)); }
        .opk-badge--partially_picked { background-color: rgb(var(--warning-600)); }
        .opk-badge--picked { background-color: rgb(var(--success-600)); }

        .opk-match--yes { border-color: rgb(var(--success-600)); background-color: rgba(var(--success-600), .1); color: rgb(var(--success-700, var(--success-600))); }
        .opk-match--no { border-color: rgb(var(--danger-600)); background-color: rgba(var(--danger-600), .1); color: rgb(var(--danger-700, var(--danger-600))); }
        :is(.dark *) .opk-match--yes { background-color: rgba(var(--success-600), .18); color: rgb(var(--success-400, var(--success-600))); }
        :is(.dark *) .opk-match--no { background-color: rgba(var(--danger-600), .18); color: rgb(var(--danger-400, var(--danger-600))); }

        .opk-heading { color: rgb(var(--gray-900)); }
        :is(.dark *) .opk-heading { color: rgb(var(--gray-50)); }

        .opk-minus { border-color: rgba(var(--danger-600), .4); background-color: rgba(var(--danger-600), .08); color: rgb(var(--danger-700, var(--danger-600))); }
        .opk-minus:hover { background-color: rgba(var(--danger-600), .15); }
        .opk-plus { border-color: rgba(var(--success-600), .4); background-color: rgba(var(--success-600), .08); color: rgb(var(--success-700, var(--success-600))); }
        .opk-plus:hover { background-color: rgba(var(--success-600), .15); }
    </style>

    <div class="mx-auto flex w-full max-w-md flex-col gap-3">

        @if (empty($warehouses))
            <div class="rounded-lg border border-gray-200 bg-white p-4 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                No active warehouses are configured. Contact an admin.
            </div>

        {{-- VIEW 3 — pick panel for the active (item, size) --}}
        @elseif ($this->activeItem && $selectedSize)
            @php $item = $this->activeItem; @endphp

            <button type="button" wire:click="backToItems" class="fi-link self-start text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                ← Back to items
            </button>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="opk-heading text-base font-bold">{{ $item->product?->name ?? 'Item' }} — {{ $selectedSize }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">SKU: {{ $item->product?->default_sku ?? '—' }}</p>

                <p class="opk-heading mt-2 text-sm font-semibold tabular-nums">
                    Ordered {{ $item->orderedQtyForSize($selectedSize) }}
                    &nbsp;·&nbsp; Picked {{ $item->pickedQtyForSize($selectedSize) }}
                    &nbsp;·&nbsp; Balance {{ $item->balanceForSize($selectedSize) }}
                </p>
            </div>

            @if ($noVariantFound)
                <div class="rounded-lg border-2 border-dashed border-gray-300 p-3 text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                    No catalog barcode/stock record found for size "{{ $selectedSize }}" — you can still record what you picked below, but warehouse stock won't be updated automatically.
                </div>
            @else
                <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <label class="mb-1 block text-sm font-semibold text-gray-600 dark:text-gray-300">Picking from warehouse</label>
                    <select wire:model.live="selectedWarehouseId" class="fi-input block w-full rounded-lg dark:bg-gray-900">
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse['id'] }}" @selected($selectedWarehouseId == $warehouse['id'])>{{ $warehouse['name'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Stock here: <span class="font-semibold">{{ $warehouseStockQty ?? 0 }}</span>
                    </p>
                </div>

                <div class="rounded-lg border-2 p-3" style="border-color: rgba(var(--primary-600), .4); background-color: rgba(var(--primary-600), .06);">
                    <label class="mb-1 block text-sm font-semibold" style="color: rgb(var(--primary-700, var(--primary-600)));">
                        Scan this size's barcode to confirm
                    </label>
                    <input
                        type="text" wire:model.live.debounce.200ms="barcodeInput"
                        wire:keydown.enter.prevent="verifyBarcode"
                        x-data x-init="$el.focus()"
                        autocomplete="off" autocapitalize="off" spellcheck="false"
                        placeholder="Ready to scan…"
                        class="fi-input block w-full rounded-lg py-3 text-lg font-semibold tracking-wide dark:bg-gray-900"
                    >
                    @if (! is_null($barcodeMatched))
                        <div class="opk-match--{{ $barcodeMatched ? 'yes' : 'no' }} mt-2 rounded-lg border-2 px-3 py-2 text-center text-sm font-bold">
                            @if ($barcodeMatched)
                                ✓ Matches — {{ $scannedProductName }} · picked {{ $stagedQty }}
                            @else
                                ✗ Wrong item{{ $scannedProductName ? " — scanned {$scannedProductName}" : ' — barcode not found' }}
                            @endif
                        </div>
                    @endif
                    @if ($itemConfirmed)
                        <p class="mt-1 text-xs" style="color: rgb(var(--primary-700, var(--primary-600)));">Scan again to add 1 more.</p>
                    @endif
                </div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 {{ $itemConfirmed ? '' : 'opacity-50' }}">
                <div class="flex items-center justify-between gap-2">
                    <span class="whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">Qty picked</span>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="adjustStagedQty(-1)" @disabled(! $itemConfirmed)
                            class="opk-minus whitespace-nowrap rounded-lg border-2 px-3 py-1.5 text-base font-bold disabled:cursor-not-allowed disabled:opacity-50">−</button>
                        <input
                            type="number" inputmode="numeric" min="0" step="1"
                            wire:model.live="stagedQty" @disabled(! $itemConfirmed)
                            class="fi-input opk-heading w-16 rounded-lg py-1.5 text-center text-2xl font-extrabold tabular-nums dark:bg-gray-900"
                        >
                        <button type="button" wire:click="adjustStagedQty(1)" @disabled(! $itemConfirmed)
                            class="opk-plus whitespace-nowrap rounded-lg border-2 px-3 py-1.5 text-base font-bold disabled:cursor-not-allowed disabled:opacity-50">+</button>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-sm font-semibold text-gray-600 dark:text-gray-300">Note (optional)</label>
                    <textarea wire:model="stagedNote" rows="2" @disabled(! $itemConfirmed)
                        class="fi-input block w-full rounded-lg dark:bg-gray-900"></textarea>
                </div>

                <button type="button" wire:click="savePick" @disabled(! $itemConfirmed)
                    class="mt-3 w-full rounded-lg bg-primary-600 py-2.5 text-base font-bold text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50">
                    Save
                </button>

                @unless ($itemConfirmed)
                    <p class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400">Scan this size's barcode above to unlock quantity and save.</p>
                @endunless
            </div>

        {{-- VIEW 2 — item list for the selected order --}}
        @elseif ($this->selectedOrder)
            @php $order = $this->selectedOrder; @endphp

            <button type="button" wire:click="backToOrders" class="fi-link self-start text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                ← Back to orders
            </button>

            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="opk-heading text-base font-bold">{{ $order->club?->name }} — Order #{{ $order->id }}</p>
            </div>

            <div class="flex flex-col gap-2">
                {{-- One line per (item, size) — e.g. "Golem Tshirt XXL" is its
                     own row separate from "Golem Tshirt L", never collapsed
                     into a single parent-item total. --}}
                @forelse ($this->pickRows as $row)
                    <button type="button" wire:click="selectItem({{ $row['item_id'] }}, @js($row['size']))"
                        class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-3 text-left shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <span>
                            <span class="opk-heading block font-semibold">{{ $row['name'] }} — {{ $row['size'] }}</span>
                            <span class="block text-xs text-gray-400 dark:text-gray-500">SKU: {{ $row['sku'] ?? '—' }}</span>
                        </span>
                        <span class="text-right text-sm">
                            <span class="opk-badge--{{ $row['state'] === 'full' ? 'picked' : ($row['state'] === 'partial' ? 'partially_picked' : 'not_picked') }} inline-block rounded-full px-2 py-0.5 text-xs font-bold text-white tabular-nums">
                                {{ $row['picked'] }} / {{ $row['ordered'] }}
                            </span>
                            <span class="block text-xs text-gray-400 dark:text-gray-500">balance {{ $row['balance'] }}</span>
                        </span>
                    </button>
                @empty
                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">No items on this order.</p>
                @endforelse
            </div>

            <button type="button" wire:click="finishPicking" wire:confirm="Finish picking this order? It will be removed from your list."
                class="w-full rounded-lg bg-primary-600 py-3 text-base font-bold text-white hover:bg-primary-500">
                Finish / Update
            </button>

        {{-- VIEW 1 — my assigned orders --}}
        @else
            <div>
                <p class="opk-heading text-lg font-bold">My Orders to Pick</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Orders assigned to you that still need picking.</p>
            </div>

            @if ($this->myOrders->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 px-3 py-6 text-center text-sm text-gray-500 dark:border-gray-600 dark:text-gray-400">
                    Nothing assigned to you right now.
                </div>
            @else
                <div class="flex flex-col gap-2">
                    @foreach ($this->myOrders as $order)
                        <button type="button" wire:click="selectOrder({{ $order->id }})"
                            class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-3 text-left shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <span>
                                <span class="opk-heading block font-semibold">{{ $order->club?->name }} — Order #{{ $order->id }}</span>
                                <span class="block text-xs text-gray-400 dark:text-gray-500">
                                    {{ $order->order_items_count }} item(s)
                                    @if ($order->picking_sent_at) · sent {{ $order->picking_sent_at->diffForHumans() }} @endif
                                </span>
                            </span>
                            <span class="opk-badge--{{ $order->picking_status }} rounded-full px-2 py-1 text-xs font-bold text-white">
                                {{ \Illuminate\Support\Str::of($order->picking_status)->replace('_', ' ')->title() }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
