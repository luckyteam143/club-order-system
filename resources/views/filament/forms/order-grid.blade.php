@php
    $initial = json_decode($getState() ?: '{}', true) ?: ['columns' => [], 'rows' => [], 'sponsors' => [], 'embellishments' => []];
@endphp

<style>
    /*
        Filament wraps a full-width form field (this one included) in a
        CSS Grid item spanning the whole row (class "col-[--col-span-default]").
        Grid/flex items default to min-width:auto, which refuses to shrink
        below their content's natural width — with a wide item-column grid
        inside, that meant THIS wrapper just grew wider than the page
        instead of our own overflow-x-auto div ever getting a chance to
        scroll internally, so the whole page scrolled and nothing stayed
        pinned. A full-row-span item never needs to resist shrinking, so
        this is safe to loosen site-wide.
    */
    .col-\[--col-span-default\] {
        min-width: 0;
    }
</style>

<div
    wire:key="order-item-grid"
    wire:ignore
    x-data="orderGrid({
        statePath: @js($getStatePath()),
        initial: @js($initial),
        products: @js($products),
        sponsorLogos: @js($sponsorLogos),
        embellishments: @js($embellishments),
        embellishmentPositions: @js($embellishmentPositions),
        packages: @js($packages),
        clubItems: @js($clubItems),
        clubItemCrests: @js($clubItemCrests),
        canEditPrices: @js($canEditPrices),
    })"
    x-init="init()"
    x-on:order-grid-saved.window="onFormSaved()"
    class="fi-order-grid min-w-0"
>
    <div x-show="localDraftAvailable" x-cloak
        class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-warning-300 dark:border-warning-800 bg-warning-50 dark:bg-warning-950 px-3 py-2 text-sm">
        <span class="text-warning-800 dark:text-warning-200">
            We found unsaved roster changes auto-saved in this browser (in case of an accidental refresh). Restore them?
        </span>
        <div class="flex gap-2 shrink-0">
            <button type="button" @click="restoreLocalDraft()"
                class="fi-btn rounded-lg bg-warning-600 px-3 py-1 text-xs font-medium text-white hover:bg-warning-500">
                Restore
            </button>
            <button type="button" @click="discardLocalDraft()"
                class="fi-btn rounded-lg bg-gray-200 dark:bg-gray-700 px-3 py-1 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600">
                Discard
            </button>
        </div>
    </div>

    <datalist id="order-grid-products">
        <template x-for="p in availableProducts()" :key="p.id">
            <option :value="p.name"></option>
        </template>
    </datalist>

    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex flex-wrap gap-2">
            <button type="button" x-show="canManageColumns()" @click="addColumn()"
                class="fi-btn inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500">
                + Add Item Column
            </button>
            <button type="button" x-show="isPackageType()" @click="loadPackageItems()"
                class="fi-btn inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                ↻ Resync Package Items
            </button>
            <p x-show="isClubItemsType() && !$wire.data.club_id" class="text-xs text-warning-600 self-center">
                Select a club above to see its assigned items.
            </p>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Tab / Enter to move between cells &middot; paste a block copied from Excel to fill many rows at once.
        </p>
    </div>

    <!--
        Three genuinely separate blocks side by side (not one table/grid
        with `position: sticky`) — sticky-in-a-scroll-container kept
        failing in practice, so instead: Player/Number/Initials (left) and
        Notes/Row Total/Actions (right) each live in their own div that is
        never inside any scrolling element, so it is physically impossible
        for them to scroll. Only the middle block (item columns) has
        `overflow-x-auto`. Being 3 separate DOM trees, they don't sync row
        heights via normal CSS — `syncHeaderHeight()` (below) measures the
        middle block's real rendered header height (which varies with
        product name length, capped at 2 lines via `line-clamp-2`) and
        mirrors it onto the other two panels' headers via `headerHeight`,
        re-running whenever a column is added/removed/changed. Body rows
        need no such syncing: every panel's row cells use identical
        single-line inputs with identical padding, so they're naturally
        the same height already.
    -->
    <!--
        Bulk Order / Forecast: no named players at all, so instead of the
        player-rows spreadsheet below, this is just "how many of each size
        per item" — one implicit row behind the scenes (see ensureBulkRow()
        in the script), rendered here as a plain item-per-row table.
    -->
    <div x-show="isBulkType()" x-cloak class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50 text-left dark:border-gray-700 dark:bg-gray-800">
                    <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Item</th>
                    <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Quantity by Size</th>
                    <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Notes</th>
                    <th class="p-2 text-right font-medium text-gray-500 dark:text-gray-400">Item Total</th>
                    <th class="w-8 p-2"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="col in columns" :key="col.key">
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="p-2 align-top">
                            <input type="text" list="order-grid-products" autocomplete="off"
                                :name="'bulk_product_' + col.key" :id="'bulk_product_' + col.key"
                                :value="productName(col.product_id)"
                                @change="onProductInput($event, col)"
                                placeholder="Type to search / change…"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="p-2">
                            <div class="flex flex-wrap gap-3">
                                <template x-for="size in productSizes(col)" :key="size">
                                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-300">
                                        <span x-text="size" class="font-medium"></span>
                                        <input type="number" min="0" step="1" autocomplete="off"
                                            :name="'bulk_qty_' + col.key + '_' + size"
                                            :value="bulkQty(col, size)"
                                            @input="setBulkQty(col, size, $event.target.value)"
                                            class="fi-input w-16 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    </label>
                                </template>
                                <span x-show="col.product_id && !productSizes(col).length" class="text-xs text-gray-400">No sizes defined for this item.</span>
                                <span x-show="!col.product_id" class="text-xs text-gray-400">Pick an item to see its sizes.</span>
                            </div>
                        </td>
                        <td class="p-2 align-top">
                            <input type="text" x-model="col.notes" @input="sync()" autocomplete="off"
                                :name="'bulk_notes_' + col.key" :id="'bulk_notes_' + col.key"
                                placeholder="Notes for this item…"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="p-2 text-right align-top tabular-nums" x-text="bulkItemTotal(col)"></td>
                        <td class="p-2 align-top text-center">
                            <button type="button" x-show="canManageColumns()" @click="removeColumn(col.key)" class="text-gray-400 hover:text-danger-600" title="Remove item">🗑</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="!columns.length">
                    <td colspan="5" class="p-3 text-center text-sm text-gray-500 dark:text-gray-400">No items yet — use "+ Add Item Column" above.</td>
                </tr>
            </tbody>
            <tfoot x-show="columns.length">
                <tr class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                    <td class="p-2 font-semibold" colspan="3">Grand Total</td>
                    <td class="p-2 text-right font-semibold tabular-nums" x-text="bulkGrandTotal()"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div x-show="!isBulkType()" class="flex items-stretch overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
        x-effect="columns.map((c) => c.product_id).join('|'); syncHeaderHeight()">
        <!-- LEFT: Player Name / Number / Initials — fixed, never scrolls -->
        <div class="grid shrink-0 border-r border-gray-200 text-sm dark:border-gray-700"
            style="grid-template-columns: 10rem 5rem 5rem">
            <div class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 text-left font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight + 'px'">Player Name</div>
            <div class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 text-left font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight + 'px'">Number</div>
            <div class="border-b border-gray-200 bg-gray-50 px-2 py-2 text-left font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight + 'px'">Initials</div>

            <template x-for="(row, rowIndex) in rows" :key="row.key">
                <div style="display: contents">
                    <div class="border-b border-r border-gray-100 p-1 dark:border-gray-800"
                        :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                        <input type="text" x-model="row.player_name" placeholder="Player name" autocomplete="off"
                            :name="'player_name_' + rowIndex" :id="'player_name_' + rowIndex"
                            :data-row="rowIndex" data-col="player_name"
                            @keydown.enter.prevent="navigate($event, 'down', true)"
                            @keydown.down.prevent="navigate($event, 'down')"
                            @keydown.up.prevent="navigate($event, 'up')"
                            @paste="onPaste($event, rowIndex, 'player_name')"
                            @input="row.player_name = row.player_name.toUpperCase(); sync()"
                            class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                    </div>
                    <div class="border-b border-r border-gray-100 p-1 dark:border-gray-800"
                        :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                        <input type="text" x-model="row.number" placeholder="#" autocomplete="off"
                            :name="'number_' + rowIndex" :id="'number_' + rowIndex"
                            :data-row="rowIndex" data-col="number"
                            @keydown.enter.prevent="navigate($event, 'down', true)"
                            @keydown.down.prevent="navigate($event, 'down')"
                            @keydown.up.prevent="navigate($event, 'up')"
                            @paste="onPaste($event, rowIndex, 'number')"
                            @input="sync()"
                            class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                    </div>
                    <div class="border-b border-gray-100 p-1 dark:border-gray-800"
                        :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                        <input type="text" x-model="row.initials" placeholder="Init." autocomplete="off"
                            :name="'initials_' + rowIndex" :id="'initials_' + rowIndex"
                            :data-row="rowIndex" data-col="initials"
                            @keydown.enter.prevent="navigate($event, 'down', true)"
                            @keydown.down.prevent="navigate($event, 'down')"
                            @keydown.up.prevent="navigate($event, 'up')"
                            @paste="onPaste($event, rowIndex, 'initials')"
                            @input="row.initials = row.initials.toUpperCase(); sync()"
                            class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                    </div>
                </div>
            </template>

            <!-- Matches the height of the middle block's mirrored scrollbar row so the footer row below stays aligned across all three blocks. -->
            <div class="h-4 border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800" style="grid-column: span 3"></div>
            <div class="border-t border-gray-200 bg-gray-50 px-2 py-2 font-semibold dark:border-gray-700 dark:bg-gray-800" style="grid-column: span 3">Grand Total</div>
        </div>

        <!-- MIDDLE: item columns — the only thing that scrolls -->
        <div class="min-w-0 flex-1 overflow-x-auto"
            x-ref="hScroll" @scroll="if ($refs.hScrollMirror) $refs.hScrollMirror.scrollLeft = $event.target.scrollLeft">
            <div class="grid text-sm" :style="'grid-template-columns: repeat(' + columns.length + ', 9rem)'">
                <template x-for="col in columns" :key="col.key">
                    <div x-ref="itemHeaderCell" class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-1">
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 whitespace-normal break-words text-xs font-semibold leading-snug text-gray-900 dark:text-gray-100"
                                    x-text="productName(col.product_id) || 'No item selected'"></p>
                                <input type="text" list="order-grid-products" autocomplete="off"
                                    :name="'product_' + col.key" :id="'product_' + col.key"
                                    :value="productName(col.product_id)"
                                    @change="onProductInput($event, col)"
                                    placeholder="Type to search / change…"
                                    class="fi-input mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs">
                            </div>
                            <button type="button" x-show="canManageColumns()" @click="removeColumn(col.key)"
                                class="shrink-0 text-gray-400 hover:text-danger-600" title="Remove item">✕</button>
                        </div>
                        <div class="mt-1 flex items-center gap-1">
                            <span class="text-xs text-gray-500">$</span>
                            <input type="number" step="0.01" x-model.number="col.unit_price" autocomplete="off"
                                :name="'unit_price_' + col.key" :id="'unit_price_' + col.key"
                                :disabled="!canEditPrices" :title="!canEditPrices ? 'Only an admin can change pricing' : null"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs disabled:cursor-not-allowed disabled:opacity-60">
                        </div>
                        {{-- Crest artwork selection is an admin/production concern — club
                             users never see it. The col.has_club_crest / col.crest_number
                             values are still carried on the column object (prefilled from
                             the club item, default crest-on / #1) and persisted to
                             order_items, they're just not editable here for a club user. --}}
                        @if ($showCrestControls)
                        <div class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                            <label class="flex items-center gap-1" title="This item carries the club crest">
                                <input type="checkbox" x-model="col.has_club_crest" @change="sync()"
                                    :name="'has_club_crest_' + col.key" :id="'has_club_crest_' + col.key"
                                    class="rounded border-gray-300 dark:border-gray-600">
                                <span>Crest</span>
                            </label>
                            <input type="number" min="1" step="1" x-show="col.has_club_crest" x-model.number="col.crest_number" @input="sync()"
                                :name="'crest_number_' + col.key" :id="'crest_number_' + col.key"
                                title="Which crest artwork (1, 2, 3…)"
                                class="fi-input w-12 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs">
                        </div>
                        @endif
                    </div>
                </template>

                <template x-for="(row, rowIndex) in rows" :key="row.key">
                    <div style="display: contents">
                        <template x-for="col in columns" :key="col.key">
                            <div class="border-b border-r border-gray-100 p-1 dark:border-gray-800"
                                :class="isCellChanged(row.key, col.key)
                                    ? 'ring-2 ring-inset ring-orange-400 bg-orange-50 dark:bg-orange-950/40'
                                    : (rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40')"
                                :title="isCellChanged(row.key, col.key) ? 'Changed in the most recent save' : null"
                                x-init="ensureCell(row, col.key)">
                                <input type="text" x-show="col.product_id" autocomplete="off"
                                    :list="'order-grid-sizes-' + rowIndex + '-' + col.key"
                                    :name="'size_' + rowIndex + '_' + col.key" :id="'size_' + rowIndex + '_' + col.key"
                                    :value="row.cells[col.key] ? row.cells[col.key].size : ''"
                                    :data-row="rowIndex" :data-col="col.key"
                                    placeholder="Size…"
                                    :class="(row.cells[col.key] && row.cells[col.key].invalid)
                                        ? 'fi-input w-full rounded-md text-sm border-danger-500 bg-danger-50 dark:bg-danger-950 focus:border-danger-500 focus:ring-danger-500'
                                        : 'fi-input w-full rounded-md text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900'"
                                    @keydown.enter.prevent="navigate($event, 'down', true)"
                                    @keydown.down.prevent="navigate($event, 'down')"
                                    @keydown.up.prevent="navigate($event, 'up')"
                                    @paste="onPaste($event, rowIndex, col.key)"
                                    @input="onSizeTyping($event, row, col)"
                                    @change="onSizeInput($event, row, col)">
                                <datalist :id="'order-grid-sizes-' + rowIndex + '-' + col.key">
                                    <template x-for="size in productSizes(col)" :key="size">
                                        <option :value="size"></option>
                                    </template>
                                </datalist>
                            </div>
                        </template>
                    </div>
                </template>

                <!--
                    A second, mirrored horizontal scrollbar sitting right
                    above Grand Total — dragging it (or the one at the
                    bottom of this block) scrolls both together via the
                    @scroll listeners on each, so it's reachable without
                    scrolling all the way down a long roster first.
                -->
                <div x-show="columns.length" class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800"
                    :style="'grid-column: span ' + columns.length"
                    x-ref="hScrollMirror" @scroll="$refs.hScroll.scrollLeft = $event.target.scrollLeft">
                    <div class="h-4" :style="'width: ' + (columns.length * 9) + 'rem'"></div>
                </div>

                <div class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800" :style="'grid-column: span ' + columns.length"></div>
            </div>
        </div>

        <!-- RIGHT: Notes / Row Total / Actions — fixed, never scrolls -->
        <div class="grid shrink-0 border-l border-gray-200 text-sm dark:border-gray-700"
            style="grid-template-columns: 10rem 6rem 40px">
            <div class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 text-left font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight + 'px'">Notes</div>
            <div class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 text-right font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight + 'px'">Row Total</div>
            <div class="border-b border-gray-200 bg-gray-50 px-2 py-2 dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight + 'px'"></div>

            <template x-for="(row, rowIndex) in rows" :key="row.key">
                <div style="display: contents">
                    <div class="border-b border-r border-gray-100 p-1 dark:border-gray-800"
                        :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                        <input type="text" x-model="row.notes" placeholder="Notes" autocomplete="off"
                            :name="'notes_' + rowIndex" :id="'notes_' + rowIndex"
                            :data-row="rowIndex" data-col="notes"
                            @keydown.enter.prevent="navigate($event, 'down', true)"
                            @keydown.down.prevent="navigate($event, 'down')"
                            @keydown.up.prevent="navigate($event, 'up')"
                            @paste="onPaste($event, rowIndex, 'notes')"
                            @input="sync()"
                            class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                    </div>
                    <div class="border-b border-r border-gray-100 p-1 text-right font-medium tabular-nums dark:border-gray-800"
                        :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'"
                        x-text="'$' + rowTotal(row).toFixed(2)"></div>
                    <div class="border-b border-gray-100 p-1 text-center whitespace-nowrap dark:border-gray-800"
                        :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                        <button type="button" @click="duplicateRow(row.key)" class="text-gray-400 hover:text-primary-600" title="Duplicate this row">⧉</button>
                        <button type="button" @click="removeRow(row.key)" class="text-gray-400 hover:text-danger-600" title="Remove row">🗑</button>
                    </div>
                </div>
            </template>

            <!-- Matches the height of the middle block's mirrored scrollbar row so the footer row below stays aligned across all three blocks. -->
            <div class="h-4 border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800" style="grid-column: span 3"></div>

            <div class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800"></div>
            <div class="border-t border-gray-200 bg-gray-50 px-2 py-2 text-right font-semibold tabular-nums dark:border-gray-700 dark:bg-gray-800"
                x-text="'$' + grandTotal().toFixed(2)"></div>
            <div class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800"></div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 mt-2" x-show="!isBulkType()">
        <button type="button" @click="addRow()"
            class="fi-btn inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
            + Add Row
        </button>

        <div class="inline-flex items-center gap-1">
            <input type="number" min="1" max="500" x-model.number="bulkAddCount" autocomplete="off"
                name="bulk_add_count" id="bulk_add_count"
                class="fi-input w-16 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
            <button type="button" @click="addRows(bulkAddCount)"
                class="fi-btn inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                + Add Rows
            </button>
        </div>
    </div>

    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700" x-show="columns.length && !isBulkType()">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Item / Size Summary</h4>
        <div class="overflow-x-auto">
            <table class="text-sm border-collapse">
                <thead>
                    <tr>
                        <th class="p-2 text-left font-medium text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">Item</th>
                        <template x-for="size in summarySizes()" :key="size">
                            <th class="p-2 text-center font-medium text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700" x-text="size"></th>
                        </template>
                        <th class="p-2 text-center font-semibold text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="col in columns" :key="col.key">
                        <tr>
                            <td class="p-2 pr-4 border-b border-gray-100 dark:border-gray-800 whitespace-nowrap" x-text="productName(col.product_id) || 'No item selected'"></td>
                            <template x-for="size in summarySizes()" :key="size">
                                <td class="p-2 text-center tabular-nums border-b border-gray-100 dark:border-gray-800" x-text="summaryCount(col, size) || '—'"></td>
                            </template>
                            <td class="p-2 text-center font-semibold tabular-nums border-b border-gray-100 dark:border-gray-800" x-text="summaryItemTotal(col)"></td>
                        </tr>
                    </template>
                </tbody>
                <tfoot x-show="summarySizes().length">
                    <tr>
                        <td class="p-2 font-semibold">Total</td>
                        <template x-for="size in summarySizes()" :key="size">
                            <td class="p-2 text-center font-semibold tabular-nums" x-text="summaryColumnTotal(size)"></td>
                        </template>
                        <td class="p-2 text-center font-semibold tabular-nums" x-text="summaryGrandTotal()"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p x-show="!summarySizes().length" class="text-xs text-gray-500 dark:text-gray-400">No sizes entered yet.</p>
    </div>

    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">Sponsor Logos</h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
            Add a sponsor logo to any item above, choose which position it prints at. An item can carry more than one logo.
        </p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700" x-show="columns.length">
            <table class="w-full text-sm border-collapse">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[12rem]">Item</th>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[12rem]">Sponsor Logo</th>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[10rem]">Position</th>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[12rem]">Brochure Link</th>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-right font-medium w-28">Override Price</th>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-right font-medium w-20">Price</th>
                        <th class="border-b border-gray-200 dark:border-gray-700 px-2 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="sponsor in sponsorRows" :key="sponsor.key">
                        <tr class="odd:bg-white even:bg-gray-50/50 dark:odd:bg-gray-900 dark:even:bg-gray-800/40">
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <select @change="sponsor.item_key = $event.target.value; sync()"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">Select item…</option>
                                    <template x-for="col in columns" :key="col.key">
                                        <option :value="col.key" :selected="col.key === sponsor.item_key" x-text="productName(col.product_id) || 'Untitled item'"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <select @change="sponsor.sponsor_logo_id = $event.target.value ? parseInt($event.target.value) : null; onSponsorLogoChange(sponsor)"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">Select logo…</option>
                                    <template x-for="logo in sponsorLogosForClub()" :key="logo.id">
                                        <option :value="logo.id" :selected="logo.id == sponsor.sponsor_logo_id" x-text="logo.name + ' ($' + logo.price.toFixed(2) + ')'"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <select @change="sponsor.embellishment_position_id = $event.target.value ? parseInt($event.target.value) : null; sync()"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">—</option>
                                    <template x-for="pos in embellishmentPositions" :key="pos.id">
                                        <option :value="pos.id" :selected="pos.id == sponsor.embellishment_position_id" x-text="pos.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <input type="text" x-model="sponsor.brochure_link" placeholder="https://…" autocomplete="off"
                                    :name="'brochure_link_' + sponsor.key" :id="'brochure_link_' + sponsor.key"
                                    @input="sync()"
                                    class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <input type="number" step="0.01" x-model.number="sponsor.override_price" autocomplete="off"
                                    :name="'sponsor_override_' + sponsor.key" :id="'sponsor_override_' + sponsor.key"
                                    placeholder="Catalog price"
                                    :disabled="!canEditPrices" :title="!canEditPrices ? 'Only an admin can change pricing' : null"
                                    @input="sync()"
                                    class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm text-right disabled:cursor-not-allowed disabled:opacity-60">
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1 text-right tabular-nums" x-text="'$' + sponsorPrice(sponsor).toFixed(2)"></td>
                            <td class="border-b border-gray-100 dark:border-gray-800 p-1 text-center">
                                <button type="button" @click="removeSponsorRow(sponsor.key)" class="text-gray-400 hover:text-danger-600" title="Remove">🗑</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2" x-show="!columns.length">Add an item column first, then attach sponsor logos to it here.</p>

        <button type="button" x-show="columns.length" @click="addSponsorRow()"
            class="fi-btn mt-2 inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
            + Add Sponsor Logo
        </button>
    </div>

    <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">Embellishments</h4>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
            Add a name/number-style embellishment to any item above, choose which position it prints at. An item can carry more than one.
        </p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700" x-show="columns.length">
            <table class="w-full text-sm border-collapse">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[12rem]">Item</th>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[12rem]">Embellishment</th>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[10rem]">Position</th>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-right font-medium w-28">Override Price</th>
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-right font-medium w-20">Total Cost</th>
                        <th class="border-b border-gray-200 dark:border-gray-700 px-2 py-2 w-10"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="embellishment in embellishmentRows" :key="embellishment.key">
                        <tr class="odd:bg-white even:bg-gray-50/50 dark:odd:bg-gray-900 dark:even:bg-gray-800/40">
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <select @change="embellishment.item_key = $event.target.value; sync()"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">Select item…</option>
                                    <template x-for="col in columns" :key="col.key">
                                        <option :value="col.key" :selected="col.key === embellishment.item_key" x-text="productName(col.product_id) || 'Untitled item'"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <select @change="embellishment.embellishment_id = $event.target.value ? parseInt($event.target.value) : null; onEmbellishmentChange(embellishment)"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">Select embellishment…</option>
                                    <template x-for="e in embellishments" :key="e.id">
                                        <option :value="e.id" :selected="e.id == embellishment.embellishment_id" x-text="e.name + ' ($' + e.cost.toFixed(2) + ')'"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <select @change="embellishment.embellishment_position_id = $event.target.value ? parseInt($event.target.value) : null; sync()"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">—</option>
                                    <template x-for="pos in embellishmentPositions" :key="pos.id">
                                        <option :value="pos.id" :selected="pos.id == embellishment.embellishment_position_id" x-text="pos.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <input type="number" step="0.01" x-model.number="embellishment.override_price" autocomplete="off"
                                    :name="'embellishment_override_' + embellishment.key" :id="'embellishment_override_' + embellishment.key"
                                    placeholder="Catalog price"
                                    :disabled="!canEditPrices" :title="!canEditPrices ? 'Only an admin can change pricing' : null"
                                    @input="sync()"
                                    class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm text-right disabled:cursor-not-allowed disabled:opacity-60">
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1 text-right tabular-nums" x-text="'$' + embellishmentPrice(embellishment).toFixed(2)"></td>
                            <td class="border-b border-gray-100 dark:border-gray-800 p-1 text-center">
                                <button type="button" @click="removeEmbellishmentRow(embellishment.key)" class="text-gray-400 hover:text-danger-600" title="Remove">🗑</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2" x-show="!columns.length">Add an item column first, then attach embellishments to it here.</p>

        <button type="button" x-show="columns.length" @click="addEmbellishmentRow()"
            class="fi-btn mt-2 inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
            + Add Embellishment
        </button>
    </div>
</div>

<script>
function orderGrid(config) {
    return {
        statePath: config.statePath,
        products: config.products,
        sponsorLogos: config.sponsorLogos,
        embellishments: config.embellishments,
        embellishmentPositions: config.embellishmentPositions,
        packages: config.packages,
        clubItems: config.clubItems,
        clubItemCrests: config.clubItemCrests,
        canEditPrices: config.canEditPrices,
        columns: [],
        rows: [],
        sponsorRows: [],
        embellishmentRows: [],
        bulkAddCount: 5,

        // Cells touched by the most recent post-submission save (see
        // EditOrder::logOrderUpdate()) — highlighted in orange so a reviewer
        // can see at a glance what changed since the order was submitted.
        changedCellKeys: new Set(),
        isCellChanged(rowKey, colKey) {
            return this.changedCellKeys.has(rowKey + ':' + colKey);
        },

        // The left/right panels are separate DOM trees from the item
        // columns (see the big comment above the roster markup), so their
        // header row height can't sync via normal CSS row-sizing — this
        // measures the item-column header's real rendered height (which
        // varies with product name length) and mirrors it onto the other
        // two panels' headers, instead of guessing a fixed px value that
        // drifts out of sync the moment a name wraps to a second line.
        headerHeight: 88,

        syncHeaderHeight() {
            this.$nextTick(() => {
                if (this.$refs.itemHeaderCell) {
                    this.headerHeight = Math.max(88, this.$refs.itemHeaderCell.offsetHeight);
                }
            });
        },

        // Per-page-URL, so Create and each Order's Edit page get their own
        // recovery slot without needing anything passed from the server.
        storageKey: 'order-grid-draft:' + window.location.pathname,
        localDraftAvailable: false,
        localDraftState: null,
        localDraftSaveTimer: null,

        // Snapshot of the whole form (not just the grid) right after load,
        // so refresh/back/close can be compared against it to decide
        // whether to warn about losing unsaved work.
        initialFormSnapshot: null,

        // Set true only when the server actually redirects (e.g. Submit
        // Order) — that page instance is being torn down either way, so it
        // never needs resetting back to false.
        formSubmitting: false,

        init() {
            this.columns = (config.initial.columns || []).map(c => ({ ...c }));
            this.rows = (config.initial.rows || []).map(r => ({ ...r, cells: { ...(r.cells || {}) } }));
            this.sponsorRows = (config.initial.sponsors || []).map(s => ({ ...s }));
            this.embellishmentRows = (config.initial.embellishments || []).map(e => ({ ...e }));
            this.changedCellKeys = new Set(config.initial.lastChangedCells || []);

            this.ensureAllCells();
            this.checkForLocalDraft();

            if (this.rows.length === 0) {
                this.addRow();
            }

            // Package orders default to one column per package item — load them
            // automatically the first time, and keep them in sync if the club/
            // package selection changes.
            if (this.columns.length === 0 && this.isPackageType() && this.$wire.data.package_id) {
                this.loadPackageItems();
            }

            this.$watch(() => this.$wire.data.package_id, () => {
                if (this.isPackageType()) this.loadPackageItems();
            });

            this.$watch(() => this.$wire.data.type, () => {
                if (this.isPackageType() && this.$wire.data.package_id) this.loadPackageItems();
            });

            // Club Items orders: if the club changes, drop any columns whose
            // product is no longer assigned to the newly-selected club.
            this.$watch(() => this.$wire.data.club_id, () => {
                if (this.isClubItemsType()) this.pruneColumnsOutsideClub();
            });

            this.$nextTick(() => {
                this.sync();
                this.initialFormSnapshot = this.currentFormSnapshot();
            });

            // Warns on refresh, back/forward navigation, and tab/window
            // close — the browser only ever shows its own generic message
            // (custom text is ignored by all modern browsers), but
            // preventDefault() + returnValue is what triggers it at all.
            window.addEventListener('beforeunload', (event) => {
                if (!this.hasUnsavedChanges()) return;
                event.preventDefault();
                event.returnValue = '';
            });

            // Submit Order (behind its own confirmation modal) follows up a
            // successful save with a hard window.location redirect — which
            // would otherwise itself trip the beforeunload guard above,
            // warning about "unsaved changes" during an intentional save.
            // Deliberately keyed off Livewire's "effect" hook and
            // specifically effects.redirect — NOT the broader "request"
            // hook, which fires for every Livewire interaction on the page
            // (e.g. just changing the Club/Type/Status dropdowns, which are
            // ->reactive()) and was suppressing the warning far too broadly,
            // for up to 10s after totally unrelated field changes. A
            // redirect effect only exists when the server actually called
            // $this->redirect(...), which is the one real case needing
            // suppression — a plain Save Draft never redirects, so it never
            // needed this at all. All "effect" listeners (Livewire's own
            // redirect handler included) run synchronously within the same
            // tick, so this always sets the flag before the browser
            // actually processes the resulting navigation.
            if (window.Livewire?.hook) {
                window.Livewire.hook('effect', ({ effects }) => {
                    if (!effects?.redirect) return;
                    this.formSubmitting = true;
                });
            }
        },

        // Compares the whole Filament form's current state (Club, Type,
        // Notes, Order Details, For Office Use, and the grid's own
        // grid_state — all live under $wire.data) against the snapshot
        // taken right after load, so this catches edits outside the grid
        // too, not just roster changes.
        currentFormSnapshot() {
            try {
                return JSON.stringify(this.$wire.data);
            } catch (e) {
                return null;
            }
        },

        hasUnsavedChanges() {
            if (this.formSubmitting) return false;
            const current = this.currentFormSnapshot();
            return current !== null && current !== this.initialFormSnapshot;
        },

        newKey(prefix) {
            return prefix + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
        },

        orderType() {
            return this.$wire.data.type;
        },

        isPackageType() {
            return this.orderType() === 'package';
        },

        isClubItemsType() {
            return this.orderType() === 'club_items';
        },

        // Order Kind (Standard/Bulk/Forecast) is a separate axis from Type
        // (Package/Individual/Club Items) — a Bulk or Forecast order still
        // uses whichever Type is selected to decide where item columns come
        // from, but always renders the no-named-players "quantity per size"
        // layout below regardless of Type.
        orderKind() {
            return this.$wire.data.order_kind || 'standard';
        },

        isBulkType() {
            return ['bulk', 'forecast'].includes(this.orderKind());
        },

        // Bulk Order / Forecast has no named players — everything lives on
        // one implicit row, and each item's cell holds a qty per size
        // ({ sizes: { M: 5, L: 2 } }) instead of a single size+qty pair.
        ensureBulkRow() {
            if (this.rows.length === 0) {
                this.rows.push({ key: this.newKey('bulk'), id: null, player_name: '', number: '', initials: '', notes: '', cells: {} });
            }

            return this.rows[0];
        },

        // Pure read — no state mutation. Alpine still evaluates the bulk
        // table's x-for bindings while it's x-show="false" (i.e. on a
        // standard, non-bulk order), and in that state the cell is in
        // {size, qty} shape with no `.sizes`, so guard every hop rather
        // than assume the bulk shape.
        bulkQty(col, size) {
            const cell = this.rows[0]?.cells[col.key];

            return (cell && cell.sizes && cell.sizes[size]) || 0;
        },

        setBulkQty(col, size, value) {
            const row = this.ensureBulkRow();
            this.ensureCell(row, col.key);
            if (!row.cells[col.key].sizes) row.cells[col.key].sizes = {};

            const qty = Math.max(0, parseInt(value) || 0);
            if (qty > 0) {
                row.cells[col.key].sizes[size] = qty;
            } else {
                delete row.cells[col.key].sizes[size];
            }

            this.sync();
        },

        bulkItemTotal(col) {
            const row = this.rows[0];
            if (!row || !row.cells[col.key]) return 0;

            return Object.values(row.cells[col.key].sizes || {}).reduce((sum, qty) => sum + (parseInt(qty) || 0), 0);
        },

        bulkGrandTotal() {
            return this.columns.reduce((total, col) => total + this.bulkItemTotal(col), 0);
        },

        // Individual and Club Items orders both let the user pick items
        // column-by-column (Package orders derive their columns from the
        // selected package instead) — this follows Type only, independent
        // of Order Kind, so a Bulk/Forecast Package order still only gets
        // its fixed package items via Resync, same as a standard one.
        canManageColumns() {
            return this.orderType() === 'individual' || this.isClubItemsType();
        },

        // The product list offered for column-picking: the full catalog for
        // Individual orders, or only the items assigned to the selected club
        // for Club Items orders (see ClubResource's "Assigned Items" field).
        availableProducts() {
            if (!this.isClubItemsType()) return this.products;

            const clubId = this.$wire.data.club_id;
            const clubPrices = (clubId && this.clubItems[clubId]) ? this.clubItems[clubId] : {};
            return this.products.filter(p => p.id in clubPrices);
        },

        // Individual orders auto-fill from the product's plain catalog
        // price; Club Items orders from that club's Online Store Price
        // (ClubResource > Assigned Items) instead — a club can define a
        // different price per product than the general catalog.
        priceForProduct(productId) {
            if (this.isClubItemsType()) {
                const clubId = this.$wire.data.club_id;
                const clubPrices = (clubId && this.clubItems[clubId]) ? this.clubItems[clubId] : {};
                if (productId in clubPrices) return clubPrices[productId];
            }

            const product = this.products.find(p => p.id == productId);
            return product ? product.price : 0;
        },

        pruneColumnsOutsideClub() {
            const allowed = this.availableProducts().map(p => p.id);
            const keptKeys = [];

            this.columns = this.columns.filter(col => {
                const keep = !col.product_id || allowed.includes(col.product_id);
                if (keep) keptKeys.push(col.key);
                return keep;
            });

            this.rows.forEach(row => {
                Object.keys(row.cells).forEach(key => {
                    if (!keptKeys.includes(key)) delete row.cells[key];
                });
            });
            this.sponsorRows = this.sponsorRows.filter(s => keptKeys.includes(s.item_key));
            this.embellishmentRows = this.embellishmentRows.filter(e => keptKeys.includes(e.item_key));

            this.sync();
        },

        sponsorLogosForClub() {
            const clubId = this.$wire.data.club_id;
            return this.sponsorLogos.filter(s => !clubId || s.club_id == clubId);
        },

        productSizes(col) {
            const product = this.products.find(p => p.id == col.product_id);
            return product ? product.sizes : [];
        },

        productName(id) {
            const product = this.products.find(p => p.id == id);
            return product ? product.name : '';
        },

        // Guarantees a row has a cell object for a given column, so template
        // bindings can always safely read/write `row.cells[col.key].size`.
        // Runs via x-init on every row×column cell as it's created, so it
        // self-heals regardless of how/when that row or column came to exist.
        ensureCell(row, colKey) {
            const expectBulkShape = this.isBulkType();
            const existing = row.cells[colKey];

            // Self-healing against switching the order Type on an unsaved
            // form (e.g. Individual → Bulk before the first save) leaving a
            // cell in the other mode's shape behind — re-initializes it to
            // match whichever mode is active now instead of erroring later
            // when bulk-mode code expects `.sizes` on an old size+qty cell.
            if (existing && (('sizes' in existing) === expectBulkShape)) return;

            row.cells[colKey] = expectBulkShape ? { sizes: {} } : { size: '', qty: 1, invalid: false };
        },

        ensureAllCells() {
            this.rows.forEach(row => {
                this.columns.forEach(col => this.ensureCell(row, col.key));
            });
        },

        // Live feedback while typing: red-highlight the cell if the current
        // text isn't empty and doesn't (yet) match any available size.
        onSizeTyping(event, row, col) {
            this.ensureCell(row, col.key);
            const typed = event.target.value.trim();
            const cell = row.cells[col.key];

            if (typed === '') {
                cell.invalid = false;
                return;
            }

            const sizes = this.productSizes(col);
            cell.invalid = !sizes.some(s => s.toLowerCase() === typed.toLowerCase());
        },

        // On commit (blur / Enter / Tab away): the stored size is always
        // either empty or an exact match — anything else is rejected and
        // reverted, so an invalid size can never be saved.
        onSizeInput(event, row, col) {
            const typed = event.target.value.trim();
            this.ensureCell(row, col.key);
            const cell = row.cells[col.key];

            if (typed === '') {
                cell.size = '';
                cell.invalid = false;
                event.target.value = '';
                this.sync();
                return;
            }

            const sizes = this.productSizes(col);
            const match = sizes.find(s => s.toLowerCase() === typed.toLowerCase());
            cell.size = match || cell.size; // reject non-matching text, keep last valid size
            cell.invalid = false;
            event.target.value = cell.size;
            this.sync();
        },

        addColumn() {
            const col = { key: this.newKey('tmp'), id: null, product_id: null, unit_price: 0, notes: '', has_club_crest: true, crest_number: 1 };
            this.columns.push(col);
            this.ensureAllCells();
            this.sync();
        },

        // The club's own crest setup for a product (ClubResource > Assigned
        // Items) — used to prefill a Club Items order column when its
        // product is picked. Returns null for other order types / unknown
        // products so the column keeps its default (crest on, #1).
        clubCrestFor(productId) {
            if (!this.isClubItemsType() || !productId) return null;
            const clubId = this.$wire.data.club_id;
            const forClub = clubId ? this.clubItemCrests[clubId] : null;
            return (forClub && forClub[productId]) ? forClub[productId] : null;
        },

        removeColumn(colKey) {
            if (!confirm('Remove this item column from the order?')) return;
            this.columns = this.columns.filter(c => c.key !== colKey);
            this.rows.forEach(row => { delete row.cells[colKey]; });
            this.sponsorRows = this.sponsorRows.filter(s => s.item_key !== colKey);
            this.embellishmentRows = this.embellishmentRows.filter(e => e.item_key !== colKey);
            this.sync();
        },

        onProductInput(event, col) {
            const typed = event.target.value.trim();
            const match = this.availableProducts().find(p => p.name.toLowerCase() === typed.toLowerCase());
            col.product_id = match ? match.id : null;
            event.target.value = this.productName(col.product_id);
            this.onProductChange(col);
        },

        onProductChange(col) {
            col.unit_price = col.product_id ? this.priceForProduct(col.product_id) : 0;

            const crest = this.clubCrestFor(col.product_id);
            if (crest) {
                col.has_club_crest = crest.has_club_crest;
                col.crest_number = crest.crest_number;
            }

            this.rows.forEach(row => {
                row.cells[col.key] = this.isBulkType() ? { sizes: {} } : { size: '', qty: 1, invalid: false };
            });
            this.sync();
        },

        addRow(shouldSync = true) {
            const row = { key: this.newKey('tmp'), id: null, player_name: '', number: '', initials: '', notes: '', cells: {} };
            this.columns.forEach(col => { row.cells[col.key] = { size: '', qty: 1, invalid: false }; });
            this.rows.push(row);
            if (shouldSync) this.sync();
        },

        addRows(count) {
            const n = Math.max(1, Math.min(500, parseInt(count) || 1));
            for (let i = 0; i < n; i++) {
                this.addRow(false);
            }
            this.sync();
        },

        // Forces every row's DOM to be fully torn down and rebuilt from
        // scratch on the next tick, instead of letting Alpine try to
        // incrementally reconcile the list in place. Used for remove/
        // duplicate specifically: those were observed acting on the wrong
        // row in the browser (deleting a just-duplicated row removed a
        // different one), which only a stale DOM-to-data binding can
        // explain. This brute-forces correctness at the cost of a brief
        // flicker, rather than continuing to guess at Alpine's internal
        // list-diffing without being able to debug it live.
        //
        // The $wire sync happens immediately, synchronously, using newRows
        // directly — deliberately NOT deferred alongside the DOM rebuild
        // below. Clicking Save right after a delete (a very natural
        // sequence) could otherwise fire the save request before the
        // deferred sync ran, sending the pre-deletion state and making the
        // row appear to "come back" after saving.
        replaceRows(newRows) {
            this.$wire.$set(this.statePath, JSON.stringify({
                columns: this.columns,
                rows: newRows,
                sponsors: this.sponsorRows,
                embellishments: this.embellishmentRows,
            }), false);
            this.saveLocalDraft();

            this.rows = [];
            this.$nextTick(() => {
                this.rows = newRows;
            });
        },

        removeRow(rowKey) {
            const row = this.rows.find(r => r.key === rowKey);
            const hasContent = row && (row.player_name || row.number || row.initials || Object.values(row.cells).some(c => c.size));
            if (hasContent && !confirm('Remove this player row?')) return;
            this.replaceRows(this.rows.filter(r => r.key !== rowKey));
        },

        // Appends a copy of a row (same player details and item sizes) to
        // the end of the list — ready to tweak (e.g. same family/teammate
        // ordering the same kit in a different size).
        duplicateRow(rowKey) {
            const source = this.rows.find(r => r.key === rowKey);
            if (!source) return;

            const copy = {
                key: this.newKey('tmp'),
                id: null,
                player_name: source.player_name,
                number: source.number,
                initials: source.initials,
                notes: source.notes,
                cells: {},
            };

            this.columns.forEach(col => {
                const src = source.cells[col.key];
                copy.cells[col.key] = { size: src ? src.size : '', qty: src ? src.qty : 1, invalid: false };
            });

            this.replaceRows([...this.rows, copy]);
        },

        // Rebuilds item columns from the selected package, and seeds the
        // Sponsor Logos / Embellishments sections from whatever the package
        // itself predefines for each item (set up on the Package page) —
        // still fully editable per-order afterward via those sections below.
        loadPackageItems() {
            const pkgId = this.$wire.data.package_id;
            const pkg = this.packages.find(p => p.id == pkgId);
            if (!pkg) return;

            this.columns = pkg.items.map(item => ({
                key: this.newKey('tmp'),
                id: null,
                product_id: item.product_id,
                unit_price: item.price,
                notes: '',
                has_club_crest: item.has_club_crest ?? true,
                crest_number: item.crest_number ?? 1,
            }));

            this.rows.forEach(row => {
                row.cells = {};
                this.columns.forEach(col => { row.cells[col.key] = { size: '', qty: 1, invalid: false }; });
            });

            this.sponsorRows = [];
            this.embellishmentRows = [];

            pkg.items.forEach((item, index) => {
                const col = this.columns[index];

                (item.sponsors || []).forEach(sponsor => {
                    this.sponsorRows.push({
                        key: this.newKey('sp'),
                        id: null,
                        item_key: col.key,
                        sponsor_logo_id: sponsor.sponsor_logo_id,
                        embellishment_position_id: sponsor.embellishment_position_id,
                        brochure_link: sponsor.brochure_link || '',
                        override_price: sponsor.override_price,
                    });
                });

                (item.embellishments || []).forEach(embellishment => {
                    this.embellishmentRows.push({
                        key: this.newKey('em'),
                        id: null,
                        item_key: col.key,
                        embellishment_id: embellishment.embellishment_id,
                        embellishment_position_id: embellishment.embellishment_position_id,
                        override_price: embellishment.override_price,
                    });
                });
            });

            this.sync();
        },

        addSponsorRow() {
            this.sponsorRows.push({
                key: this.newKey('sp'),
                id: null,
                item_key: this.columns[0]?.key || '',
                sponsor_logo_id: null,
                embellishment_position_id: null,
                brochure_link: '',
                override_price: null,
            });
            this.sync();
        },

        removeSponsorRow(key) {
            this.sponsorRows = this.sponsorRows.filter(s => s.key !== key);
            this.sync();
        },

        onSponsorLogoChange(sponsor) {
            const logo = this.sponsorLogos.find(l => l.id == sponsor.sponsor_logo_id);
            if (logo && !sponsor.embellishment_position_id) {
                sponsor.embellishment_position_id = logo.position_id;
            }
            this.sync();
        },

        sponsorPrice(sponsor) {
            if (sponsor.override_price !== null && sponsor.override_price !== '' && !isNaN(parseFloat(sponsor.override_price))) {
                return parseFloat(sponsor.override_price);
            }
            const logo = this.sponsorLogos.find(l => l.id == sponsor.sponsor_logo_id);
            return logo ? parseFloat(logo.price) : 0;
        },

        addEmbellishmentRow() {
            this.embellishmentRows.push({
                key: this.newKey('em'),
                id: null,
                item_key: this.columns[0]?.key || '',
                embellishment_id: null,
                embellishment_position_id: null,
                override_price: null,
            });
            this.sync();
        },

        removeEmbellishmentRow(key) {
            this.embellishmentRows = this.embellishmentRows.filter(e => e.key !== key);
            this.sync();
        },

        onEmbellishmentChange(embellishment) {
            const catalogEntry = this.embellishments.find(e => e.id == embellishment.embellishment_id);
            if (catalogEntry && !embellishment.embellishment_position_id) {
                embellishment.embellishment_position_id = catalogEntry.position_id;
            }
            this.sync();
        },

        embellishmentPrice(embellishment) {
            if (embellishment.override_price !== null && embellishment.override_price !== '' && !isNaN(parseFloat(embellishment.override_price))) {
                return parseFloat(embellishment.override_price);
            }
            const catalogEntry = this.embellishments.find(e => e.id == embellishment.embellishment_id);
            return catalogEntry ? parseFloat(catalogEntry.cost) : 0;
        },

        // Sponsor/embellishment add-on cost for a column only (no base unit
        // price) — these always apply per item regardless of kit pricing.
        colAddOnsUnitCost(col) {
            const sponsorsTotal = this.sponsorRows
                .filter(s => s.item_key === col.key && s.sponsor_logo_id)
                .reduce((sum, s) => sum + this.sponsorPrice(s), 0);
            const embellishmentsTotal = this.embellishmentRows
                .filter(e => e.item_key === col.key && e.embellishment_id)
                .reduce((sum, e) => sum + this.embellishmentPrice(e), 0);
            return sponsorsTotal + embellishmentsTotal;
        },

        colUnitCost(col) {
            return (parseFloat(col.unit_price) || 0) + this.colAddOnsUnitCost(col);
        },

        cellTotal(row, colKey) {
            const cell = row.cells[colKey];
            if (!cell || !cell.size) return 0;
            const col = this.columns.find(c => c.key === colKey);
            if (!col) return 0;
            return this.colUnitCost(col) * (parseInt(cell.qty) || 1);
        },

        cellAddOnsTotal(row, colKey) {
            const cell = row.cells[colKey];
            if (!cell || !cell.size) return 0;
            const col = this.columns.find(c => c.key === colKey);
            if (!col) return 0;
            return this.colAddOnsUnitCost(col) * (parseInt(cell.qty) || 1);
        },

        // The flat kit price for the selected package, if the order is a
        // Package order and that package has a price defined — null
        // otherwise, meaning "fall back to summing individual item prices".
        packagePrice() {
            if (this.orderType() !== 'package') return null;
            const pkg = this.packages.find(p => p.id == this.$wire.data.package_id);
            return (pkg && parseFloat(pkg.price) > 0) ? parseFloat(pkg.price) : null;
        },

        rowHasAnyItem(row) {
            return this.columns.some(col => {
                const cell = row.cells[col.key];
                return cell && cell.size;
            });
        },

        rowTotal(row) {
            const kitPrice = this.packagePrice();

            if (kitPrice !== null) {
                if (!this.rowHasAnyItem(row)) return 0;
                const addOns = this.columns.reduce((sum, col) => sum + this.cellAddOnsTotal(row, col.key), 0);
                return kitPrice + addOns;
            }

            return this.columns.reduce((sum, col) => sum + this.cellTotal(row, col.key), 0);
        },

        grandTotal() {
            return this.rows.reduce((sum, row) => sum + this.rowTotal(row), 0);
        },

        // Item × size summary table — every distinct size actually entered
        // anywhere in the roster, as columns, against each item as rows.
        summarySizes() {
            const sizes = new Set();
            this.rows.forEach(row => {
                Object.values(row.cells || {}).forEach(cell => {
                    if (cell && cell.size) sizes.add(cell.size);
                });
            });
            return Array.from(sizes).sort();
        },

        summaryCount(col, size) {
            return this.rows.reduce((total, row) => {
                const cell = row.cells[col.key];
                return (cell && cell.size === size) ? total + (parseInt(cell.qty) || 0) : total;
            }, 0);
        },

        summaryItemTotal(col) {
            return this.rows.reduce((total, row) => {
                const cell = row.cells[col.key];
                return (cell && cell.size) ? total + (parseInt(cell.qty) || 0) : total;
            }, 0);
        },

        summaryColumnTotal(size) {
            return this.columns.reduce((total, col) => total + this.summaryCount(col, size), 0);
        },

        summaryGrandTotal() {
            return this.columns.reduce((total, col) => total + this.summaryItemTotal(col), 0);
        },

        allColKeys() {
            return ['player_name', 'number', 'initials', ...this.columns.map(c => c.key), 'notes'];
        },

        // Excel-style vertical navigation: Enter/ArrowDown move to the same
        // column in the next row; Enter specifically adds a new row when
        // you're on the last one (a deliberate "give me the next line"
        // action), but plain ArrowDown never creates rows on its own —
        // it's pure navigation, same as ArrowUp. Left/right movement is
        // left to the browser's native Tab/Shift+Tab and text-cursor
        // behavior.
        navigate(event, direction, allowAutoAddRow = false) {
            const el = event.target;
            const row = parseInt(el.dataset.row);
            const col = el.dataset.col;
            const targetRow = direction === 'down' ? row + 1 : row - 1;

            if (targetRow < 0) {
                return;
            }

            if (targetRow >= this.rows.length) {
                if (!allowAutoAddRow) {
                    return;
                }
                this.addRow();
            }

            this.$nextTick(() => {
                // Deliberately document.* here, not this.$el — inside a
                // method invoked from a directive on the cell <input>
                // itself, Alpine's $el resolves to that input (the element
                // the directive is bound to), not the component's root
                // container, so this.$el.querySelectorAll() was always
                // searching a leaf node with no children and finding
                // nothing. Also not a CSS-selector lookup: CSS.escape() is
                // for bare identifiers, not values already inside
                // attribute-selector quotes, which fails for values
                // starting with a digit. Comparing .dataset directly
                // sidesteps both problems.
                const next = Array.from(document.querySelectorAll('[data-row]'))
                    .find(node => node.dataset.row === String(targetRow) && node.dataset.col === String(col));
                if (!next) return;
                next.focus();
                if (typeof next.select === 'function') next.select();
            });
        },

        setCellValue(rowIndex, colKey, value) {
            const row = this.rows[rowIndex];
            if (!row) return;

            if (['player_name', 'initials'].includes(colKey)) {
                row[colKey] = value.toUpperCase();
                return;
            }

            if (['number', 'notes'].includes(colKey)) {
                row[colKey] = value;
                return;
            }

            const col = this.columns.find(c => c.key === colKey);
            if (!col) return;

            this.ensureCell(row, colKey);
            const cell = row.cells[colKey];
            if (value === '') {
                cell.size = '';
                return;
            }

            const sizes = this.productSizes(col);
            const match = sizes.find(s => s.toLowerCase() === value.toLowerCase());
            cell.size = match || cell.size;
            cell.qty = cell.qty || 1;
        },

        onPaste(event, rowIndex, colKey) {
            const text = (event.clipboardData || window.clipboardData).getData('text');
            if (!text || (!text.includes('\t') && !text.includes('\n'))) {
                return; // let the browser handle a plain single-value paste
            }

            event.preventDefault();

            const colOrder = this.allColKeys();
            const startColIndex = colOrder.indexOf(colKey);
            const lines = text.replace(/\r/g, '').split('\n').filter((line, i, arr) => !(i === arr.length - 1 && line === ''));

            lines.forEach((line, rOffset) => {
                const values = line.split('\t');
                let targetRow = rowIndex + rOffset;
                while (targetRow >= this.rows.length) this.addRow();

                values.forEach((value, cOffset) => {
                    const targetColKey = colOrder[startColIndex + cOffset];
                    if (!targetColKey) return;
                    this.setCellValue(targetRow, targetColKey, value.trim());
                });
            });

            this.sync();
        },

        serializeState() {
            return JSON.stringify({
                columns: this.columns,
                rows: this.rows,
                sponsors: this.sponsorRows,
                embellishments: this.embellishmentRows,
            });
        },

        // Strips identity fields (key/id, and cell dicts keyed by column
        // key) down to positional indexes before comparing two serialized
        // states — a column/row saved before its first real Save carries a
        // temporary "tmp..." key that becomes a stable DB-backed key
        // ("i123"/"p456") once saved. Comparing raw JSON would treat
        // otherwise-identical data as "different" forever after that first
        // save, permanently showing a false "unsaved changes" recovery
        // banner that Restore can't meaningfully act on. Used only to
        // decide whether to show that banner — restoring itself still uses
        // the untouched original snapshot.
        normalizeStateForCompare(rawJson) {
            try {
                const data = JSON.parse(rawJson);
                const columnKeyToIndex = {};
                (data.columns || []).forEach((c, i) => { columnKeyToIndex[c.key] = i; });

                const columns = (data.columns || []).map(c => ({
                    product_id: c.product_id,
                    unit_price: c.unit_price,
                    has_club_crest: c.has_club_crest,
                    crest_number: c.crest_number,
                }));

                const rows = (data.rows || []).map(r => {
                    const cells = {};
                    Object.entries(r.cells || {}).forEach(([colKey, cell]) => {
                        const index = columnKeyToIndex[colKey];
                        if (index !== undefined) cells[index] = { size: cell.size, qty: cell.qty };
                    });
                    return {
                        player_name: r.player_name, number: r.number,
                        initials: r.initials, notes: r.notes, cells,
                    };
                });

                const sponsors = (data.sponsors || []).map(s => ({
                    item_index: columnKeyToIndex[s.item_key],
                    sponsor_logo_id: s.sponsor_logo_id,
                    embellishment_position_id: s.embellishment_position_id,
                    brochure_link: s.brochure_link,
                    override_price: s.override_price,
                }));

                const embellishments = (data.embellishments || []).map(e => ({
                    item_index: columnKeyToIndex[e.item_key],
                    embellishment_id: e.embellishment_id,
                    embellishment_position_id: e.embellishment_position_id,
                    override_price: e.override_price,
                }));

                return JSON.stringify({ columns, rows, sponsors, embellishments });
            } catch (e) {
                return rawJson;
            }
        },

        // Updates the Livewire component's local state only — no request.
        // Livewire still picks this up correctly whenever the real Save/
        // Submit Order action fires, since it diffs the component's full
        // current state against the server's last snapshot at that point,
        // not just properties explicitly marked dirty beforehand.
        //
        // Deliberately never fires a request from inside the grid itself:
        // the grid's root element is wire:ignore'd (see the root <div>) so
        // Livewire never re-renders/morphs it out from under an in-progress
        // interaction, but an explicit background flush would still cause a
        // real round trip elsewhere on the page — not worth the risk for a
        // "durability" gain Save already provides.
        sync() {
            this.$wire.$set(this.statePath, this.serializeState(), false);
            this.saveLocalDraft();
        },

        // Best-effort recovery net against an accidental refresh/tab-close:
        // mirrors the grid's own state (the hardest part of this page to
        // retype — a whole roster) into localStorage, debounced so rapid
        // typing/paste doesn't hammer it. Entirely client-side — never
        // touches Livewire, so it can't reintroduce the wire:ignore/morph
        // issue this grid was built to avoid.
        saveLocalDraft() {
            clearTimeout(this.localDraftSaveTimer);
            this.localDraftSaveTimer = setTimeout(() => {
                try {
                    localStorage.setItem(this.storageKey, JSON.stringify({
                        savedAt: Date.now(),
                        state: this.serializeState(),
                    }));
                } catch (e) {
                    // localStorage unavailable/full — recovery is best-effort only.
                }
            }, 800);
        },

        // Only offers to restore when the saved snapshot actually differs
        // from what the server just sent down — e.g. a draft saved after
        // this page's last real Save. Otherwise silently cleans itself up.
        checkForLocalDraft() {
            let raw;
            try {
                raw = localStorage.getItem(this.storageKey);
            } catch (e) {
                return;
            }
            if (!raw) return;

            let saved;
            try {
                saved = JSON.parse(raw);
            } catch (e) {
                try { localStorage.removeItem(this.storageKey); } catch (e2) {}
                return;
            }
            if (!saved || !saved.state) return;

            if (this.normalizeStateForCompare(saved.state) === this.normalizeStateForCompare(this.serializeState())) {
                try { localStorage.removeItem(this.storageKey); } catch (e) {}
                return;
            }

            this.localDraftAvailable = true;
            this.localDraftState = saved.state;
        },

        restoreLocalDraft() {
            let data;
            try {
                data = JSON.parse(this.localDraftState);
            } catch (e) {
                this.discardLocalDraft();
                return;
            }

            this.columns = (data.columns || []).map(c => ({ ...c }));
            this.rows = (data.rows || []).map(r => ({ ...r, cells: { ...(r.cells || {}) } }));
            this.sponsorRows = (data.sponsors || []).map(s => ({ ...s }));
            this.embellishmentRows = (data.embellishments || []).map(e => ({ ...e }));
            this.ensureAllCells();

            this.localDraftAvailable = false;
            this.sync();
        },

        discardLocalDraft() {
            // Without this, a debounced saveLocalDraft() already in flight
            // from before the click fires shortly after and silently
            // re-creates the entry — the draft "coming back" after discard.
            clearTimeout(this.localDraftSaveTimer);
            try { localStorage.removeItem(this.storageKey); } catch (e) {}
            this.localDraftAvailable = false;
        },

        // Fired after a real Save/Create succeeds (see EditOrder::afterSave
        // / CreateOrder::afterCreate dispatching 'order-grid-saved'): clears
        // the recovery snapshot and moves the "unsaved changes" baseline
        // forward, so the beforeunload warning doesn't fire right after a
        // successful save.
        onFormSaved() {
            this.discardLocalDraft();
            this.initialFormSnapshot = this.currentFormSnapshot();
        },
    };
}
</script>
