@php
    $initial = json_decode($getState() ?: '{}', true) ?: ['columns' => [], 'rows' => []];
@endphp

<div
    x-data="orderGrid({
        statePath: @js($getStatePath()),
        initial: @js($initial),
        products: @js($products),
        sponsorLogos: @js($sponsorLogos),
        embellishments: @js($embellishments),
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
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 align-top min-w-[13rem]">
                            <div class="flex items-start justify-between gap-1">
                                <input type="text" list="order-grid-products"
                                    :value="productName(col.product_id)"
                                    @change="onProductInput($event, col)"
                                    placeholder="Type to search product…"
                                    class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs font-semibold">
                                <button type="button" x-show="isIndividual()" @click="removeColumn(col.key)"
                                    class="shrink-0 text-gray-400 hover:text-danger-600" title="Remove item">✕</button>
                            </div>
                            <select x-model.number="col.sponsor_logo_id"
                                class="fi-select mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs">
                                <option value="">No sponsor logo</option>
                                <template x-for="s in sponsorLogosForClub()" :key="s.id">
                                    <option :value="s.id" x-text="s.name + ' (+$' + s.price.toFixed(2) + ')'"></option>
                                </template>
                            </select>
                            <select x-model.number="col.embellishment_id"
                                class="fi-select mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs">
                                <option value="">No embellishment</option>
                                <template x-for="e in embellishments" :key="e.id">
                                    <option :value="e.id" x-text="e.name + ' (+$' + e.cost.toFixed(2) + ')'"></option>
                                </template>
                            </select>
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
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <select x-show="col.product_id" :data-row="rowIndex" :data-col="col.key"
                                    x-model="row.cells[col.key].size"
                                    @keydown.enter.prevent="moveDown($event)"
                                    @paste="onPaste($event, rowIndex, col.key)"
                                    @change="sync()"
                                    class="fi-select w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                    <option value="">—</option>
                                    <template x-for="size in productSizes(col)" :key="size">
                                        <option :value="size" x-text="size"></option>
                                    </template>
                                </select>
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
</div>

<script>
function orderGrid(config) {
    return {
        statePath: config.statePath,
        products: config.products,
        sponsorLogos: config.sponsorLogos,
        embellishments: config.embellishments,
        packages: config.packages,
        columns: [],
        rows: [],

        init() {
            this.columns = (config.initial.columns || []).map(c => ({ ...c }));
            this.rows = (config.initial.rows || []).map(r => ({ ...r, cells: { ...(r.cells || {}) } }));

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

        // Guarantees every row has a cell object for every column, so template
        // bindings can always safely read/write `row.cells[col.key].size`
        // without racing the reactive array/object mutations that add rows or
        // columns.
        ensureAllCells() {
            this.rows.forEach(row => {
                this.columns.forEach(col => {
                    if (!row.cells[col.key]) {
                        row.cells[col.key] = { size: '', qty: 1 };
                    }
                });
            });
        },

        cellFor(row, colKey) {
            if (!row.cells[colKey]) {
                row.cells[colKey] = { size: '', qty: 1 };
            }

            return row.cells[colKey];
        },

        addColumn() {
            const col = { key: this.newKey('tmp'), id: null, product_id: null, sponsor_logo_id: null, embellishment_id: null, unit_price: 0 };
            this.columns.push(col);
            this.ensureAllCells();
            this.sync();
        },

        removeColumn(colKey) {
            if (!confirm('Remove this item column from the order?')) return;
            this.columns = this.columns.filter(c => c.key !== colKey);
            this.rows.forEach(row => { delete row.cells[colKey]; });
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
            this.rows.forEach(row => { row.cells[col.key] = { size: '', qty: 1 }; });
            this.sync();
        },

        addRow() {
            const row = { key: this.newKey('tmp'), id: null, player_name: '', number: '', initials: '', notes: '', cells: {} };
            this.columns.forEach(col => { row.cells[col.key] = { size: '', qty: 1 }; });
            this.rows.push(row);
            this.sync();
        },

        removeRow(rowKey) {
            const row = this.rows.find(r => r.key === rowKey);
            const hasContent = row && (row.player_name || row.number || row.initials || Object.values(row.cells).some(c => c.size));
            if (hasContent && !confirm('Remove this player row?')) return;
            this.rows = this.rows.filter(r => r.key !== rowKey);
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
                sponsor_logo_id: null,
                embellishment_id: null,
                unit_price: item.price,
            }));

            this.rows.forEach(row => {
                row.cells = {};
                this.columns.forEach(col => { row.cells[col.key] = { size: '', qty: 1 }; });
            });

            this.sync();
        },

        colUnitCost(col) {
            const embellishment = this.embellishments.find(e => e.id == col.embellishment_id);
            const sponsor = this.sponsorLogos.find(s => s.id == col.sponsor_logo_id);
            return (parseFloat(col.unit_price) || 0)
                + (embellishment ? parseFloat(embellishment.cost) : 0)
                + (sponsor ? parseFloat(sponsor.price) : 0);
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

            const cell = this.cellFor(row, colKey);
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

        sync() {
            this.$wire.$set(this.statePath, JSON.stringify({ columns: this.columns, rows: this.rows }), false);
        },
    };
}
</script>
