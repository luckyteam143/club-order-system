@php
    $initial = json_decode($getState() ?: '{}', true) ?: ['columns' => [], 'rows' => [], 'sponsors' => []];
@endphp

<div
    x-data="orderGrid({
        statePath: @js($getStatePath()),
        initial: @js($initial),
        products: @js($products),
        sponsorLogos: @js($sponsorLogos),
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
                                @keydown.enter.prevent="moveDown($event)"
                                @paste="onPaste($event, rowIndex, 'player_name')"
                                @input="sync()"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.number" placeholder="#"
                                :data-row="rowIndex" data-col="number"
                                @keydown.enter.prevent="moveDown($event)"
                                @paste="onPaste($event, rowIndex, 'number')"
                                @input="sync()"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.initials" placeholder="Init."
                                :data-row="rowIndex" data-col="initials"
                                @keydown.enter.prevent="moveDown($event)"
                                @paste="onPaste($event, rowIndex, 'initials')"
                                @input="sync()"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>

                        <template x-for="col in columns" :key="col.key">
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1" x-init="ensureCell(row, col.key)">
                                <input type="text" x-show="col.product_id"
                                    :list="'order-grid-sizes-' + col.key"
                                    :value="row.cells[col.key] ? row.cells[col.key].size : ''"
                                    :data-row="rowIndex" :data-col="col.key"
                                    placeholder="Size…"
                                    :class="(row.cells[col.key] && row.cells[col.key].invalid)
                                        ? 'fi-input w-full rounded-md text-sm border-danger-500 bg-danger-50 dark:bg-danger-950 focus:border-danger-500 focus:ring-danger-500'
                                        : 'fi-input w-full rounded-md text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900'"
                                    @keydown.enter.prevent="moveDown($event)"
                                    @paste="onPaste($event, rowIndex, col.key)"
                                    @input="onSizeTyping($event, row, col)"
                                    @change="onSizeInput($event, row, col)">
                                <datalist :id="'order-grid-sizes-' + col.key">
                                    <template x-for="size in productSizes(col)" :key="size">
                                        <option :value="size"></option>
                                    </template>
                                </datalist>
                            </td>
                        </template>

                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.notes" placeholder="Notes"
                                :data-row="rowIndex" data-col="notes"
                                @keydown.enter.prevent="moveDown($event)"
                                @paste="onPaste($event, rowIndex, 'notes')"
                                @input="sync()"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1 text-right font-medium tabular-nums" x-text="'$' + rowTotal(row).toFixed(2)"></td>
                        <td class="border-b border-gray-100 dark:border-gray-800 p-1 text-center">
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

    <button type="button" @click="addRow()"
        class="fi-btn mt-2 inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
        + Add Row
    </button>

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
                                <select x-model="sponsor.item_key" @change="syncNow()"
                                    x-init="$nextTick(() => { $el.value = sponsor.item_key || '' })"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">Select item…</option>
                                    <template x-for="col in columns" :key="col.key">
                                        <option :value="col.key" x-text="productName(col.product_id) || 'Untitled item'"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <select x-model.number="sponsor.sponsor_logo_id" @change="onSponsorLogoChange(sponsor)"
                                    x-init="$nextTick(() => { $el.value = sponsor.sponsor_logo_id || '' })"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">Select logo…</option>
                                    <template x-for="logo in sponsorLogosForClub()" :key="logo.id">
                                        <option :value="logo.id" x-text="logo.name + ' ($' + logo.price.toFixed(2) + ')'"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <select x-model.number="sponsor.embellishment_position_id" @change="syncNow()"
                                    x-init="$nextTick(() => { $el.value = sponsor.embellishment_position_id || '' })"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">—</option>
                                    <template x-for="pos in embellishmentPositions" :key="pos.id">
                                        <option :value="pos.id" x-text="pos.name"></option>
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
</div>

<script>
function orderGrid(config) {
    return {
        statePath: config.statePath,
        products: config.products,
        sponsorLogos: config.sponsorLogos,
        embellishmentPositions: config.embellishmentPositions,
        packages: config.packages,
        columns: [],
        rows: [],
        sponsorRows: [],

        init() {
            this.columns = (config.initial.columns || []).map(c => ({ ...c }));
            this.rows = (config.initial.rows || []).map(r => ({ ...r, cells: { ...(r.cells || {}) } }));
            this.sponsorRows = (config.initial.sponsors || []).map(s => ({ ...s }));

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
            this.syncNow();
        },

        removeColumn(colKey) {
            if (!confirm('Remove this item column from the order?')) return;
            this.columns = this.columns.filter(c => c.key !== colKey);
            this.rows.forEach(row => { delete row.cells[colKey]; });
            this.sponsorRows = this.sponsorRows.filter(s => s.item_key !== colKey);
            this.syncNow();
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
            this.syncNow();
        },

        addRow() {
            const row = { key: this.newKey('tmp'), id: null, player_name: '', number: '', initials: '', notes: '', cells: {} };
            this.columns.forEach(col => { row.cells[col.key] = { size: '', qty: 1, invalid: false }; });
            this.rows.push(row);
            this.sync();
        },

        removeRow(rowKey) {
            const row = this.rows.find(r => r.key === rowKey);
            const hasContent = row && (row.player_name || row.number || row.initials || Object.values(row.cells).some(c => c.size));
            if (hasContent && !confirm('Remove this player row?')) return;
            this.rows = this.rows.filter(r => r.key !== rowKey);
            this.syncNow();
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

            this.syncNow();
        },

        addSponsorRow() {
            this.sponsorRows.push({
                key: this.newKey('sp'),
                id: null,
                item_key: this.columns[0]?.key || '',
                sponsor_logo_id: null,
                embellishment_position_id: null,
            });
            this.syncNow();
        },

        removeSponsorRow(key) {
            this.sponsorRows = this.sponsorRows.filter(s => s.key !== key);
            this.syncNow();
        },

        onSponsorLogoChange(sponsor) {
            const logo = this.sponsorLogos.find(l => l.id == sponsor.sponsor_logo_id);
            if (logo && !sponsor.embellishment_position_id) {
                sponsor.embellishment_position_id = logo.position_id;
            }
            this.syncNow();
        },

        sponsorPrice(sponsor) {
            const logo = this.sponsorLogos.find(l => l.id == sponsor.sponsor_logo_id);
            return logo ? parseFloat(logo.price) : 0;
        },

        colUnitCost(col) {
            const sponsorsTotal = this.sponsorRows
                .filter(s => s.item_key === col.key && s.sponsor_logo_id)
                .reduce((sum, s) => sum + this.sponsorPrice(s), 0);
            return (parseFloat(col.unit_price) || 0) + sponsorsTotal;
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

        moveDown(event) {
            const el = event.target;
            const row = parseInt(el.dataset.row);
            const col = el.dataset.col;

            if (row === this.rows.length - 1) {
                this.addRow();
            }

            this.$nextTick(() => {
                const next = this.$el.querySelector(`[data-row="${row + 1}"][data-col="${CSS.escape(col)}"]`);
                if (next) next.focus();
            });
        },

        setCellValue(rowIndex, colKey, value) {
            const row = this.rows[rowIndex];
            if (!row) return;

            if (['player_name', 'number', 'initials', 'notes'].includes(colKey)) {
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
        sync() {
            const json = JSON.stringify({ columns: this.columns, rows: this.rows, sponsors: this.sponsorRows });
            this.$wire.$set(this.statePath, json, false);

            clearTimeout(this._flushTimer);
            this._flushTimer = setTimeout(() => {
                this.$wire.$set(this.statePath, json, true);
            }, 800);
        },

        // Same as sync(), but flushes to the server immediately instead of
        // waiting out the debounce — used for infrequent, deliberate actions
        // (adding/removing columns, rows, sponsor logos; picking a product or
        // sponsor logo) where there's no reason to wait and every reason to
        // want it durably saved right away.
        syncNow() {
            clearTimeout(this._flushTimer);
            const json = JSON.stringify({ columns: this.columns, rows: this.rows, sponsors: this.sponsorRows });
            this.$wire.$set(this.statePath, json, true);
        },
    };
}
</script>
