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

    /*
        CreateOrder/EditOrder already override getMaxContentWidth() to drop
        Filament's default 7xl (80rem) cap, but the page's own left/right
        padding (px-4 md:px-6 lg:px-8, baked into Filament's compiled CSS —
        not overridable via a Tailwind class here) still eats real width
        from the item-column grid on top of that. :has() scopes this to
        just the page this field is on, so it never touches any other
        Filament page's padding.
    */
    .fi-main:has(.fi-order-grid) {
        max-width: 100% !important;
        padding-left: 1rem !important;
        padding-right: 1rem !important;
    }
</style>

<div
    wire:key="order-item-grid"
    wire:ignore
    x-data="orderGrid({
        statePath: @js($getStatePath()),
        initial: @js($initial),
        products: @js($products),
        sizeOrder: @js($sizeOrder),
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
        failing in practice, so instead: Player/Initials/Number (left) and
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
                    @if ($showCrestControls)
                    <th class="p-2 font-medium text-gray-500 dark:text-gray-400">Crest</th>
                    @endif
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
                        @if ($showCrestControls)
                        <td class="p-2 align-top">
                            <div class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                <label class="flex items-center gap-1" title="This item carries the club crest">
                                    <input type="checkbox" x-model="col.has_club_crest" @change="sync()"
                                        :name="'has_club_crest_' + col.key" :id="'has_club_crest_' + col.key"
                                        class="rounded border-gray-300 dark:border-gray-600">
                                    <span>Crest</span>
                                </label>
                                <input type="number" min="1" step="1" x-show="col.has_club_crest" x-model.number="col.crest_number" @input="sync()"
                                    :name="'crest_number_' + col.key" :id="'crest_number_' + col.key"
                                    title="Which crest artwork (1, 2, 3…)"
                                    class="fi-input w-9 !px-1 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs">
                            </div>
                        </td>
                        @endif
                        <td class="p-2 text-right align-top tabular-nums" x-text="bulkItemTotal(col)"></td>
                        <td class="p-2 align-top text-center">
                            <button type="button" x-show="canManageColumns()" @click="removeColumn(col.key)" class="text-gray-400 hover:text-danger-600" title="Remove item">🗑</button>
                        </td>
                    </tr>
                </template>
                <tr x-show="!columns.length">
                    <td colspan="{{ $showCrestControls ? 6 : 5 }}" class="p-3 text-center text-sm text-gray-500 dark:text-gray-400">No items yet — use "+ Add Item Column" above.</td>
                </tr>
            </tbody>
            <tfoot x-show="columns.length">
                <tr class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                    <td class="p-2 font-semibold" colspan="3">Grand Total</td>
                    @if ($showCrestControls)
                    <td></td>
                    @endif
                    <td class="p-2 text-right font-semibold tabular-nums" x-text="bulkGrandTotal()"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!--
        Package orders whose selected package has at least one item flagged
        Goalkeeper Item (PackageResource > Products in Package) get a
        second, separate roster block above the regular one: its own
        columns (only that package's goalkeeper-flagged items), its own
        rows (a goalkeeper is a different roster entry than an outfield
        player), and its own independent Add/Remove/Duplicate Row controls
        — see order-grid-roster.blade.php. Every other order type (and a
        package with no goalkeeper items) only ever renders the Player
        Items block, unchanged from before this existed. Item/Size Summary
        and the Sponsor Logos / Embellishments sections below both still
        iterate the single flat `columns`/`rows` arrays, so they cover both
        blocks automatically without any extra wiring.
    -->
    <div x-show="!isBulkType()">
        <template x-if="isPackageType() && hasGoalieItems()">
            <div class="mb-6">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Goalkeeper Items</h4>
                @include('filament.forms.partials.order-grid-roster', ['section' => 'goalie'])
            </div>
        </template>

        <div>
            <h4 x-show="isPackageType() && hasGoalieItems()" class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">Player Items</h4>
            @include('filament.forms.partials.order-grid-roster', ['section' => 'player'])
        </div>

        <div x-show="isPackageType() && hasGoalieItems()" class="mt-2 flex justify-end">
            <div class="rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-2 text-sm font-semibold">
                Grand Total (Goalkeeper + Player): <span x-text="'$' + grandTotal().toFixed(2)"></span>
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button type="button" @click="saveDraft()"
            class="fi-btn inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-500">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            Save Draft
        </button>
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
        <p x-show="!summarySizes().length" class="text-xs text-gray-500 dark:text-gray-400">No items with sizes on this order yet.</p>
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
        sizeOrder: config.sizeOrder,
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
        // Keyed by section ('player' | 'goalie') — each roster block gets
        // its own independent "+ Add Rows" count.
        bulkAddCount: { player: 5, goalie: 5 },

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
        // Keyed by section — the Goalkeeper and Player blocks are separate
        // DOM trees with independently-sized headers.
        headerHeight: { player: 88, goalie: 88 },

        syncHeaderHeight(section) {
            this.$nextTick(() => {
                const ref = this.$refs['itemHeaderCell_' + section];
                if (ref) {
                    this.headerHeight[section] = Math.max(88, ref.offsetHeight);
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

        // The order Type / Package the item grid was last built against.
        // Both the type and package watchers compare against these (rather
        // than Alpine's occasionally-stale oldValue) so they can tell a
        // real user change apart from the echo of a programmatic revert,
        // and know what to put the dropdown back to when a "this clears
        // your items" prompt is cancelled.
        appliedType: null,
        appliedPackageId: null,

        init() {
            this.columns = (config.initial.columns || []).map(c => ({ ...c }));
            this.rows = (config.initial.rows || []).map(r => ({ ...r, cells: { ...(r.cells || {}) } }));
            this.sponsorRows = (config.initial.sponsors || []).map(s => ({ ...s }));
            this.embellishmentRows = (config.initial.embellishments || []).map(e => ({ ...e }));
            this.changedCellKeys = new Set(config.initial.lastChangedCells || []);

            this.ensureAllCells();
            this.checkForLocalDraft();

            // Package orders default to one column per package item — load them
            // automatically the first time, and keep them in sync if the club/
            // package selection changes.
            if (this.columns.length === 0 && this.isPackageType() && this.$wire.data.package_id) {
                this.loadPackageItems();
            }

            this.ensureDefaultRows();

            this.appliedType = this.$wire.data.type ?? null;
            this.appliedPackageId = this.$wire.data.package_id ?? null;

            // Changing the Package rebuilds the item columns from the newly
            // selected package and wipes everything entered against the old
            // ones — same destructive reload the type watcher guards, so
            // warn first whenever the grid already has columns, and put the
            // dropdown back if the user cancels.
            this.$watch(() => this.$wire.data.package_id, (newPkg) => {
                if (! this.isPackageType()) { this.appliedPackageId = newPkg ?? null; return; }
                if ((newPkg ?? null) === this.appliedPackageId) return;

                if (newPkg && this.columns.length > 0 && ! confirm(
                    'Changing the package rebuilds the item columns from the new package and '
                    + 'clears everything entered against the current items — sizes, quantities, '
                    + 'sponsor logos and embellishments. Continue?'
                )) {
                    this.revertSelect('data.package_id', this.appliedPackageId);
                    return;
                }

                this.loadPackageItems();
            });

            // Changing the order Type invalidates the current item columns
            // (a Package's items mean nothing on an Individual / Club Items
            // order, and vice versa). Warn before throwing away anything the
            // user has entered, and revert the dropdown if they cancel;
            // otherwise reset the item section to the new type's starting
            // layout.
            this.$watch(() => this.$wire.data.type, (newType) => {
                if ((newType ?? null) === this.appliedType) return;

                if (this.columns.length > 0 && ! confirm(
                    'Switching the order type clears the current item list and everything entered '
                    + 'against it — sizes, quantities, sponsor logos and embellishments. Continue?'
                )) {
                    this.revertSelect('data.type', this.appliedType);
                    return;
                }

                this.resetItemSection();
            });

            // Switching Order Kind to/from Bulk or Forecast doesn't rebuild
            // the columns, but it does change whether the flat item list is
            // in use — re-group so goalkeeper-only items sit at the end.
            this.$watch(() => this.$wire.data.order_kind, () => {
                if (this.isPackageType()) this.groupGoalieItemsLast();
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

            // Ctrl/Cmd+S = Save Draft, handled entirely here rather than
            // relying on Filament's ->keyBindings(['mod+s']) on the header
            // button: that binding lives outside this wire:ignore'd grid and
            // proved unreliable from inside it (the browser's own "Save
            // page" dialog kept winning). Capture phase on window runs
            // before everything else, so preventDefault() reliably kills the
            // browser dialog and stopPropagation() keeps any header-button
            // key binding from also firing (no double save). saveDraft()
            // then commits the focused cell and calls the page's save.
            window.addEventListener('keydown', (event) => {
                const isSaveCombo = (event.ctrlKey || event.metaKey) && (event.key === 's' || event.key === 'S');
                if (!isSaveCombo) return;

                event.preventDefault();
                event.stopPropagation();
                this.saveDraft();
            }, true);

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
                this.columnsFor(row.section || 'player').forEach(col => this.ensureCell(row, col.key));
            });
        },

        // Section-scoping: `columns` and `rows` stay single flat arrays
        // (so totals/summary/sponsors below keep iterating everything and
        // stay naturally combined) — these two just filter which of them
        // belong in the Goalkeeper vs Player roster block. A column can be
        // flagged as both and so appear in both blocks sharing one
        // order_item; a row (a named person) only ever belongs to one.
        columnsFor(section) {
            return section === 'goalie'
                ? this.columns.filter(c => c.is_goalie_item === true)
                : this.columns.filter(c => c.is_player_item === true);
        },

        sectionRows(section) {
            return section === 'goalie'
                ? this.rows.filter(r => r.section === 'goalie')
                : this.rows.filter(r => r.section !== 'goalie');
        },

        // Gates the whole Goalkeeper Items block — only ever true for a
        // Package order whose selected package has at least one item
        // flagged Goalkeeper Item.
        hasGoalieItems() {
            return this.columnsFor('goalie').length > 0;
        },

        sectionTotal(section) {
            return this.sectionRows(section).reduce((sum, row) => sum + this.rowTotal(row), 0);
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
            // Individual / Club Items columns are never goalkeeper-only —
            // that classification only exists on package items.
            const col = { key: this.newKey('tmp'), id: null, product_id: null, unit_price: 0, notes: '', has_club_crest: true, crest_number: 1, is_goalie_item: false, is_player_item: true, number_color: null };
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

        addRow(section = 'player', shouldSync = true) {
            const row = { key: this.newKey('tmp'), id: null, player_name: '', number: '', initials: '', notes: '', section, cells: {} };
            this.columnsFor(section).forEach(col => { row.cells[col.key] = { size: '', qty: 1, invalid: false }; });
            this.rows.push(row);
            if (shouldSync) this.sync();
        },

        // Guarantees a starter row per visible (non-bulk) section: the
        // Player section always starts with one blank row (unchanged from
        // before the Goalkeeper split existed), and the Goalkeeper Items
        // section gets its own starter row the moment it becomes visible
        // (initial load, switching to a package with goalkeeper items, or
        // Resync Package Items) instead of opening empty. A starter row
        // that's never actually filled in is dropped on save (see
        // PersistsOrderGrid's blank-row skip), so calling this is always
        // safe even if it ends up unused.
        ensureDefaultRows() {
            if (this.rows.length === 0) {
                this.addRow('player', false);
            }

            if (!this.isBulkType() && this.hasGoalieItems() && this.sectionRows('goalie').length === 0) {
                this.addRow('goalie', false);
            }
        },

        addRows(section, count) {
            const n = Math.max(1, Math.min(500, parseInt(count) || 1));
            for (let i = 0; i < n; i++) {
                this.addRow(section, false);
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

            const section = source.section || 'player';

            const copy = {
                key: this.newKey('tmp'),
                id: null,
                player_name: source.player_name,
                number: source.number,
                initials: source.initials,
                notes: source.notes,
                section,
                cells: {},
            };

            this.columnsFor(section).forEach(col => {
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
            // Record what the grid is now built against even when we bail
            // out below (package cleared / not in the catalog), so the
            // watcher doesn't re-prompt on the next change.
            this.appliedPackageId = pkgId ?? null;
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
                is_goalie_item: item.is_goalie_item ?? false,
                is_player_item: item.is_player_item ?? true,
                number_color: item.number_color ?? null,
            }));

            // Each row only gets cells for its own section's columns — a
            // goalkeeper row never carries phantom cells for player-only
            // items, and vice versa.
            this.rows.forEach(row => {
                row.cells = {};
                this.columnsFor(row.section || 'player').forEach(col => { row.cells[col.key] = { size: '', qty: 1, invalid: false }; });
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

            // Sponsor/embellishment seeding above pairs pkg.items[index] with
            // columns[index], so only reorder the columns once that's done.
            this.groupGoalieItemsLast();

            this.ensureDefaultRows();
            this.sync();
        },

        // Bulk Order / Forecast renders one flat item list with no separate
        // Goalkeeper roster block, so push the goalkeeper-only items to the
        // end instead of leaving them wherever the package's sort order put
        // them. Items flagged as both Goalkeeper and Player stay inline with
        // the rest. No-op for every other order kind. Reorders by object
        // reference, so sponsor/embellishment rows (keyed by col.key) and
        // per-row cell maps are unaffected.
        groupGoalieItemsLast() {
            if (!this.isBulkType()) return;

            const goalieOnly = c => c.is_goalie_item === true && c.is_player_item !== true;
            const head = this.columns.filter(c => !goalieOnly(c));
            const tail = this.columns.filter(goalieOnly);

            if (tail.length === 0) return;
            if (tail.every((c, i) => this.columns[head.length + i] === c)) return;

            this.columns = [...head, ...tail];
            this.sync();
        },

        // Wipes the whole item section — columns, roster rows, and the
        // per-item Sponsor Logos / Embellishments — then rebuilds the
        // starting layout for whatever order Type is now selected: package
        // items for a Package order with a package chosen, otherwise a
        // single blank starter row. Used when the Type changes, where the
        // old type's columns make no sense against the new one.
        resetItemSection() {
            this.columns = [];
            this.sponsorRows = [];
            this.embellishmentRows = [];
            this.changedCellKeys = new Set();
            this.rows = [];
            this.appliedType = this.$wire.data.type ?? null;

            if (this.isPackageType() && this.$wire.data.package_id) {
                this.loadPackageItems();
            } else {
                this.appliedPackageId = this.$wire.data.package_id ?? null;
                this.ensureDefaultRows();
                this.ensureAllCells();
                this.sync();
            }
        },

        // Put a Filament <select> back to a previous value after the user
        // cancels a destructive prompt. Deferred a tick so our revert
        // commit lands after the live-binding commit from the change we're
        // undoing — doing it synchronously inside the watcher raced that
        // commit and the field could stay stuck on the cancelled option.
        revertSelect(statePath, value) {
            setTimeout(() => { this.$wire.$set(statePath, value ?? null); }, 0);
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

        // Item × size summary table — every size any item in the order
        // actually offers (not just the ones someone has picked so far),
        // as columns, against each item as rows. Sorted against sizeOrder
        // (every size name in Attribute position order, same source
        // OrderExport's own $sizeOrder uses) rather than merging each
        // column's own already-correctly-ordered size list one column at a
        // time — that merge only comes out right when every item shares an
        // identical size set; with different subsets per item it can
        // produce a wrong order (e.g. S, M, L, XS instead of XS, S, M, L).
        summarySizes() {
            const present = new Set();
            this.columns.forEach(col => {
                this.productSizes(col).forEach(size => present.add(size));
            });

            const ordered = this.sizeOrder.filter(size => present.has(size));
            // Defensive: a size present on a product but somehow missing
            // from sizeOrder (shouldn't happen — sizeOrder is every
            // Attribute there is) still shows up, just appended at the end
            // rather than silently dropped.
            const unordered = Array.from(present).filter(size => ! this.sizeOrder.includes(size));

            return [...ordered, ...unordered];
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

        // Column order used for a horizontal paste block — scoped to one
        // section's own columns, since the Goalkeeper and Player blocks
        // are separate tables with separate row indexes.
        allColKeysFor(section) {
            return ['player_name', 'initials', 'number', ...this.columnsFor(section).map(c => c.key), 'notes'];
        },

        // Excel-style vertical navigation: Enter/ArrowDown move to the same
        // column in the next row; Enter specifically adds a new row when
        // you're on the last one (a deliberate "give me the next line"
        // action), but plain ArrowDown never creates rows on its own —
        // it's pure navigation, same as ArrowUp. Left/right movement is
        // left to the browser's native Tab/Shift+Tab and text-cursor
        // behavior. Every cell also carries a data-section attribute (see
        // order-grid-roster.blade.php) so this never jumps between the
        // Goalkeeper and Player blocks even though both re-use row indexes
        // starting at 0.
        navigate(event, direction, allowAutoAddRow = false) {
            const el = event.target;
            const section = el.dataset.section || 'player';
            const row = parseInt(el.dataset.row);
            const col = el.dataset.col;
            const targetRow = direction === 'down' ? row + 1 : row - 1;

            if (targetRow < 0) {
                return;
            }

            if (targetRow >= this.sectionRows(section).length) {
                if (!allowAutoAddRow) {
                    return;
                }
                this.addRow(section);
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
                    .find(node => node.dataset.row === String(targetRow) && node.dataset.col === String(col) && (node.dataset.section || 'player') === section);
                if (!next) return;
                next.focus();
                if (typeof next.select === 'function') next.select();
            });
        },

        // Takes the row object directly (not an index) — with two sections
        // sharing row-index numbering, an index alone is ambiguous.
        setCellValue(row, colKey, value) {
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

            const section = event.target.dataset.section || 'player';
            const colOrder = this.allColKeysFor(section);
            const startColIndex = colOrder.indexOf(colKey);
            const lines = text.replace(/\r/g, '').split('\n').filter((line, i, arr) => !(i === arr.length - 1 && line === ''));

            lines.forEach((line, rOffset) => {
                const values = line.split('\t');
                const targetIndex = rowIndex + rOffset;
                while (this.sectionRows(section).length <= targetIndex) this.addRow(section, false);
                const targetRow = this.sectionRows(section)[targetIndex];

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
                    is_goalie_item: c.is_goalie_item,
                    is_player_item: c.is_player_item,
                    number_color: c.number_color ?? null,
                }));

                const rows = (data.rows || []).map(r => {
                    const cells = {};
                    Object.entries(r.cells || {}).forEach(([colKey, cell]) => {
                        const index = columnKeyToIndex[colKey];
                        if (index !== undefined) cells[index] = { size: cell.size, qty: cell.qty };
                    });
                    return {
                        player_name: r.player_name, number: r.number,
                        initials: r.initials, notes: r.notes, section: r.section, cells,
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

        // Size and product cells only push their value to $wire on
        // blur/change, so blur whatever's focused inside the grid before a
        // save so the in-progress cell isn't left out of the request.
        commitActiveCell() {
            const active = document.activeElement;
            if (active && active !== document.body && this.$root.contains(active) && typeof active.blur === 'function') {
                active.blur();
            }
        },

        // Shared by the in-grid "Save Draft" button (above the Item / Size
        // Summary) and the Ctrl/Cmd+S shortcut. Calls the Filament page's
        // own save method directly — save() on the Edit page, create() on
        // the Create / Create Bulk pages (both end their path with /create
        // or /create-bulk, Edit ends with /edit) — which is exactly what the
        // header "Save Draft" button does, so notifications and redirect
        // behaviour stay identical. commitActiveCell() first so a size /
        // product cell being edited (those only push to $wire on blur) is
        // included in the request.
        saving: false,
        saveDraft() {
            if (this.saving) return;
            this.saving = true;

            this.commitActiveCell();

            this.$nextTick(() => {
                const method = window.location.pathname.endsWith('/edit') ? 'save' : 'create';

                Promise.resolve(this.$wire[method]()).finally(() => {
                    this.saving = false;
                });
            });
        },
    };
}
</script>
