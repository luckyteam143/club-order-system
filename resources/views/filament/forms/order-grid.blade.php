@php
    $initial = json_decode($getState() ?: '{}', true) ?: ['columns' => [], 'rows' => [], 'sponsors' => [], 'embellishments' => []];
@endphp

<div
    x-data="orderGrid({
        statePath: @js($getStatePath()),
        initial: @js($initial),
        products: @js($products),
        sponsorLogos: @js($sponsorLogos),
        embellishments: @js($embellishments),
        embellishmentPositions: @js($embellishmentPositions),
        packages: @js($packages),
    })"
    x-init="init()"
    class="fi-order-grid"
>
    <datalist id="order-grid-products">
        <template x-for="p in products" :key="p.id">
            <option :value="p.name"></option>
        </template>
    </datalist>

    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex flex-wrap gap-2">
            <button type="button" x-show="isIndividual()" @click="addColumn()"
                class="fi-btn inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500">
                + Add Item Column
            </button>
            <button type="button" x-show="!isIndividual()" @click="loadPackageItems()"
                class="fi-btn inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                ↻ Resync Package Items
            </button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Tab / Enter to move between cells &middot; paste a block copied from Excel to fill many rows at once.
        </p>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm border-collapse">
            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                <tr>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[10rem]">Player Name</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium w-20">Number</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium w-20">Initials</th>

                    <template x-for="col in columns" :key="col.key">
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 align-top min-w-[12rem]">
                            <div class="flex items-start justify-between gap-1">
                                <input type="text" list="order-grid-products"
                                    :value="productName(col.product_id)"
                                    @change="onProductInput($event, col)"
                                    placeholder="Type to search product…"
                                    class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs font-semibold">
                                <button type="button" x-show="isIndividual()" @click="removeColumn(col.key)"
                                    class="shrink-0 text-gray-400 hover:text-danger-600" title="Remove item">✕</button>
                            </div>
                            <div class="mt-1 flex items-center gap-1">
                                <span class="text-xs text-gray-500">$</span>
                                <input type="number" step="0.01" x-model.number="col.unit_price"
                                    class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs">
                            </div>
                        </th>
                    </template>

                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[10rem]">Notes</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-right font-medium w-24">Row Total</th>
                    <th class="border-b border-gray-200 dark:border-gray-700 px-2 py-2 w-10"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(row, rowIndex) in rows" :key="row.key">
                    <tr class="odd:bg-white even:bg-gray-50/50 dark:odd:bg-gray-900 dark:even:bg-gray-800/40">
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.player_name" placeholder="Player name"
                                :data-row="rowIndex" data-col="player_name"
                                @keydown.enter.prevent="navigate($event, 'down')"
                                @keydown.down.prevent="navigate($event, 'down')"
                                @keydown.up.prevent="navigate($event, 'up')"
                                @paste="onPaste($event, rowIndex, 'player_name')"
                                @input="row.player_name = row.player_name.toUpperCase(); sync()"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.number" placeholder="#"
                                :data-row="rowIndex" data-col="number"
                                @keydown.enter.prevent="navigate($event, 'down')"
                                @keydown.down.prevent="navigate($event, 'down')"
                                @keydown.up.prevent="navigate($event, 'up')"
                                @paste="onPaste($event, rowIndex, 'number')"
                                @input="sync()"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.initials" placeholder="Init."
                                :data-row="rowIndex" data-col="initials"
                                @keydown.enter.prevent="navigate($event, 'down')"
                                @keydown.down.prevent="navigate($event, 'down')"
                                @keydown.up.prevent="navigate($event, 'up')"
                                @paste="onPaste($event, rowIndex, 'initials')"
                                @input="row.initials = row.initials.toUpperCase(); sync()"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>

                        <template x-for="col in columns" :key="col.key">
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1" x-init="ensureCell(row, col.key)">
                                <input type="text" x-show="col.product_id"
                                    :list="'order-grid-sizes-' + rowIndex + '-' + col.key"
                                    :value="row.cells[col.key] ? row.cells[col.key].size : ''"
                                    :data-row="rowIndex" :data-col="col.key"
                                    placeholder="Size…"
                                    :class="(row.cells[col.key] && row.cells[col.key].invalid)
                                        ? 'fi-input w-full rounded-md text-sm border-danger-500 bg-danger-50 dark:bg-danger-950 focus:border-danger-500 focus:ring-danger-500'
                                        : 'fi-input w-full rounded-md text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900'"
                                    @keydown.enter.prevent="navigate($event, 'down')"
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
                            </td>
                        </template>

                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.notes" placeholder="Notes"
                                :data-row="rowIndex" data-col="notes"
                                @keydown.enter.prevent="navigate($event, 'down')"
                                @keydown.down.prevent="navigate($event, 'down')"
                                @keydown.up.prevent="navigate($event, 'up')"
                                @paste="onPaste($event, rowIndex, 'notes')"
                                @input="sync()"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1 text-right font-medium tabular-nums" x-text="'$' + rowTotal(row).toFixed(2)"></td>
                        <td class="border-b border-gray-100 dark:border-gray-800 p-1 text-center whitespace-nowrap">
                            <button type="button" @click="duplicateRow(row.key)" class="text-gray-400 hover:text-primary-600" title="Duplicate this row">⧉</button>
                            <button type="button" @click="removeRow(row.key)" class="text-gray-400 hover:text-danger-600" title="Remove row">🗑</button>
                        </td>
                    </tr>
                </template>
            </tbody>
            <tfoot>
                <tr class="bg-gray-50 dark:bg-gray-800 font-semibold">
                    <td class="px-2 py-2 border-t border-gray-200 dark:border-gray-700" :colspan="3 + columns.length + 1">Grand Total</td>
                    <td class="px-2 py-2 border-t border-gray-200 dark:border-gray-700 text-right tabular-nums" x-text="'$' + grandTotal().toFixed(2)"></td>
                    <td class="border-t border-gray-200 dark:border-gray-700"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex flex-wrap items-center gap-2 mt-2">
        <button type="button" @click="addRow()"
            class="fi-btn inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
            + Add Row
        </button>

        <div class="inline-flex items-center gap-1">
            <input type="number" min="1" max="500" x-model.number="bulkAddCount"
                class="fi-input w-16 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
            <button type="button" @click="addRows(bulkAddCount)"
                class="fi-btn inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                + Add Rows
            </button>
        </div>
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
        columns: [],
        rows: [],
        sponsorRows: [],
        embellishmentRows: [],
        bulkAddCount: 5,

        init() {
            this.columns = (config.initial.columns || []).map(c => ({ ...c }));
            this.rows = (config.initial.rows || []).map(r => ({ ...r, cells: { ...(r.cells || {}) } }));
            this.sponsorRows = (config.initial.sponsors || []).map(s => ({ ...s }));
            this.embellishmentRows = (config.initial.embellishments || []).map(e => ({ ...e }));

            this.ensureAllCells();

            if (this.rows.length === 0) {
                this.addRow();
            }

            // Package orders default to one column per package item — load them
            // automatically the first time, and keep them in sync if the club/
            // package selection changes.
            if (this.columns.length === 0 && !this.isIndividual() && this.$wire.data.package_id) {
                this.loadPackageItems();
            }

            this.$watch(() => this.$wire.data.package_id, () => {
                if (!this.isIndividual()) this.loadPackageItems();
            });

            this.$watch(() => this.$wire.data.type, () => {
                if (!this.isIndividual() && this.$wire.data.package_id) this.loadPackageItems();
            });

            this.$nextTick(() => this.sync());
        },

        newKey(prefix) {
            return prefix + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
        },

        isIndividual() {
            return this.$wire.data.type !== 'package';
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
            if (!row.cells[colKey]) {
                row.cells[colKey] = { size: '', qty: 1, invalid: false };
            }
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
            const col = { key: this.newKey('tmp'), id: null, product_id: null, unit_price: 0 };
            this.columns.push(col);
            this.ensureAllCells();
            this.sync();
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
            const match = this.products.find(p => p.name.toLowerCase() === typed.toLowerCase());
            col.product_id = match ? match.id : null;
            event.target.value = this.productName(col.product_id);
            this.onProductChange(col);
        },

        onProductChange(col) {
            const product = this.products.find(p => p.id == col.product_id);
            col.unit_price = product ? product.price : 0;
            this.rows.forEach(row => { row.cells[col.key] = { size: '', qty: 1, invalid: false }; });
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

        removeRow(rowKey) {
            const row = this.rows.find(r => r.key === rowKey);
            const hasContent = row && (row.player_name || row.number || row.initials || Object.values(row.cells).some(c => c.size));
            if (hasContent && !confirm('Remove this player row?')) return;
            this.rows = this.rows.filter(r => r.key !== rowKey);
            this.sync();
        },

        // Inserts a copy of a row (same player details and item sizes)
        // directly below the source row — ready to tweak (e.g. same
        // family/teammate ordering the same kit in a different size).
        //
        // Appended to the end rather than inserted right after the source:
        // inserting mid-array (via splice, or via a full reassigned array)
        // leaves this template's row-tracking out of sync with which DOM row
        // is bound to which array item — confirmed by duplicating/removing
        // acting on the wrong row. Appending is the one approach that's held
        // up reliably, so correctness wins over exact position here.
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

            this.rows.push(copy);
            this.sync();
        },

        loadPackageItems() {
            const pkgId = this.$wire.data.package_id;
            const pkg = this.packages.find(p => p.id == pkgId);
            if (!pkg) return;

            this.columns = pkg.items.map(item => ({
                key: this.newKey('tmp'),
                id: null,
                product_id: item.product_id,
                unit_price: item.price,
            }));

            this.rows.forEach(row => {
                row.cells = {};
                this.columns.forEach(col => { row.cells[col.key] = { size: '', qty: 1, invalid: false }; });
            });

            const columnKeys = this.columns.map(c => c.key);
            this.sponsorRows = this.sponsorRows.filter(s => columnKeys.includes(s.item_key));
            this.embellishmentRows = this.embellishmentRows.filter(e => columnKeys.includes(e.item_key));

            this.sync();
        },

        addSponsorRow() {
            this.sponsorRows.push({
                key: this.newKey('sp'),
                id: null,
                item_key: this.columns[0]?.key || '',
                sponsor_logo_id: null,
                embellishment_position_id: null,
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
            const catalogEntry = this.embellishments.find(e => e.id == embellishment.embellishment_id);
            return catalogEntry ? parseFloat(catalogEntry.cost) : 0;
        },

        colUnitCost(col) {
            const sponsorsTotal = this.sponsorRows
                .filter(s => s.item_key === col.key && s.sponsor_logo_id)
                .reduce((sum, s) => sum + this.sponsorPrice(s), 0);
            const embellishmentsTotal = this.embellishmentRows
                .filter(e => e.item_key === col.key && e.embellishment_id)
                .reduce((sum, e) => sum + this.embellishmentPrice(e), 0);
            return (parseFloat(col.unit_price) || 0) + sponsorsTotal + embellishmentsTotal;
        },

        cellTotal(row, colKey) {
            const cell = row.cells[colKey];
            if (!cell || !cell.size) return 0;
            const col = this.columns.find(c => c.key === colKey);
            if (!col) return 0;
            return this.colUnitCost(col) * (parseInt(cell.qty) || 1);
        },

        rowTotal(row) {
            return this.columns.reduce((sum, col) => sum + this.cellTotal(row, col.key), 0);
        },

        grandTotal() {
            return this.rows.reduce((sum, row) => sum + this.rowTotal(row), 0);
        },

        allColKeys() {
            return ['player_name', 'number', 'initials', ...this.columns.map(c => c.key), 'notes'];
        },

        // Excel-style vertical navigation: Enter/ArrowDown move to the same
        // column in the next row (adding one if you're on the last row),
        // ArrowUp moves to the previous row. Left/right movement is left to
        // the browser's native Tab/Shift+Tab and text-cursor behavior.
        navigate(event, direction) {
            const el = event.target;
            const row = parseInt(el.dataset.row);
            const col = el.dataset.col;
            const targetRow = direction === 'down' ? row + 1 : row - 1;

            if (targetRow < 0) {
                return;
            }

            if (targetRow >= this.rows.length) {
                if (direction !== 'down') {
                    return;
                }
                this.addRow();
            }

            this.$nextTick(() => {
                const next = this.$el.querySelector(`[data-row="${targetRow}"][data-col="${CSS.escape(col)}"]`);
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

        // Updates the Livewire component's local state immediately (no request),
        // AND schedules a real, debounced flush to the server shortly after —
        // so the grid's data is durably saved server-side within ~1s of the
        // last edit, not just held in the browser waiting for some other
        // action (like the Save button) to happen to bundle it along.
        serializeState() {
            return JSON.stringify({
                columns: this.columns,
                rows: this.rows,
                sponsors: this.sponsorRows,
                embellishments: this.embellishmentRows,
            });
        },

        // Updates the Livewire component's local state immediately (no
        // request — this is what the Save button reads when clicked, so it
        // always reflects the latest edit regardless of timing), and
        // schedules a real, debounced flush to the server shortly after so
        // the data is durably stored even before Save is clicked.
        //
        // Deliberately never fires a *live* (immediate) request from inside
        // the grid: an immediate request re-renders and lets Livewire morph
        // the DOM while the user may still be interacting with it, which can
        // destroy/recreate the exact Alpine scope a selection handler (or a
        // still-pending x-init $nextTick callback) is referencing —
        // that's what was behind the "embellishment is not defined" crash.
        sync() {
            const json = this.serializeState();
            this.$wire.$set(this.statePath, json, false);

            clearTimeout(this._flushTimer);
            this._flushTimer = setTimeout(() => {
                this.$wire.$set(this.statePath, json, true);
            }, 800);
        },
    };
}
</script>
