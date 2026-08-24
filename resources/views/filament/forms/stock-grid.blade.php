<div
    wire:key="stock-grid"
    wire:ignore
    x-data="stockGrid({
        statePath: 'gridState',
        warehouses: @js($warehouses),
        initial: @js($initial),
        initialSearch: @js($initialSearch ?? ''),
    })"
    x-init="init()"
    class="fi-stock-grid"
>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-3">
            <input type="text" x-ref="search" x-model="searchQuery" name="stock_grid_search" id="stock_grid_search"
                @input="onSearchInput()"
                placeholder="Search product name, barcode, or size to show matching rows…"
                autocomplete="off"
                class="fi-input w-96 max-w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
            <span x-show="searching" x-cloak class="text-xs text-gray-400 dark:text-gray-500">Searching…</span>
            <span x-show="editedCount() > 0" x-cloak
                class="text-xs font-medium text-primary-600 dark:text-primary-400">
                <span x-text="editedCount()"></span> product(s) with pending changes
            </span>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Edits are kept even if you change the search &middot; paste a block copied from Excel to fill many rows at once.
        </p>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm border-collapse">
            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                <tr>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[16rem]">Product</th>
                    <template x-for="w in warehouses" :key="w.id">
                        <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-right font-medium w-28" x-text="w.name"></th>
                    </template>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-right font-medium w-24">Total</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(p, rowIndex) in visibleProducts" :key="p.id">
                    <tr x-init="ensureProductState(p.id)"
                        class="odd:bg-white even:bg-gray-50/50 dark:odd:bg-gray-900 dark:even:bg-gray-800/40">
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-2 font-medium" x-text="productLabel(p)"></td>
                        <template x-for="w in warehouses" :key="w.id">
                            <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                                <input type="text" inputmode="numeric" autocomplete="off"
                                    x-model="pendingEdits[p.id].cells[w.id].qty"
                                    :data-row="rowIndex" :data-col="w.id"
                                    :name="'stock_qty_' + p.id + '_' + w.id"
                                    :id="'stock_qty_' + p.id + '_' + w.id"
                                    @keydown="onCellKeydown($event)"
                                    @paste="onPaste($event, rowIndex, w.id)"
                                    @input="markTouched(p.id); sync()"
                                    class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm text-right">
                            </td>
                        </template>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-2 text-right font-medium tabular-nums" x-text="rowTotal(p.id)"></td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2" x-show="searchQuery.trim().length < 2">
        Type at least 2 characters above to show matching products here — edit their stock inline, for as many products as you like, then hit Save Stock.
    </p>
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2" x-show="searchQuery.trim().length >= 2 && visibleProducts.length === 0">
        No matching products.
    </p>
</div>

<script>
function stockGrid(config) {
    return {
        statePath: config.statePath,
        warehouses: config.warehouses,
        searchQuery: '',
        visibleProducts: [],
        pendingEdits: {}, // productId -> { cells: { warehouseId: { qty, id } } }
        loadingIds: {},
        // Distinct from pendingEdits' keys, which also include products
        // merely shown (with prefilled existing stock) but never actually
        // typed into — this tracks genuine user edits for the counter.
        touchedIds: {},
        searching: false,
        searchTimer: null,
        // Bumped on every new search so a slow, now-stale response can't
        // clobber the results of a more recent one typed after it.
        searchToken: 0,

        init() {
            this.pendingEdits = { ...(config.initial || {}) };
            Object.values(this.pendingEdits).forEach(state => {
                state.cells = { ...(state.cells || {}) };
            });

            if (config.initialSearch) {
                this.searchQuery = config.initialSearch;
                this.onSearchInput();
            }
        },

        productLabel(p) {
            let label = p.name;
            // Child (size-variant) product names usually already bake the
            // size in (e.g. "BANJO HERO HOODY BLK 3XL") — only append it
            // separately when it isn't already part of the name.
            if (p.size && !label.toUpperCase().includes(p.size.toUpperCase())) {
                label += ' - ' + p.size;
            }
            if (p.barcode) {
                label += ' (' + p.barcode + ')';
            }
            return label;
        },

        // Matches show up directly as editable rows (no separate "add"
        // step), same as the WooCommerce Advanced Bulk Edit-style product
        // tables. Searched server-side (debounced) rather than filtering a
        // client-side array — the catalog runs into the tens of thousands
        // of rows once every size variant is counted, which made an
        // embedded-and-filtered approach slow to load and to type into.
        onSearchInput() {
            clearTimeout(this.searchTimer);
            const query = this.searchQuery.trim();

            if (query.length < 2) {
                this.searching = false;
                this.visibleProducts = [];
                return;
            }

            this.searching = true;
            this.searchTimer = setTimeout(() => this.runSearch(query), 300);
        },

        runSearch(query) {
            const token = ++this.searchToken;

            this.$wire.call('searchProducts', query).then(results => {
                if (token !== this.searchToken) return; // superseded by a newer search

                this.visibleProducts = results || [];
                this.searching = false;
                this.loadMissingStock();
            });
        },

        ensureProductState(productId) {
            if (!this.pendingEdits[productId]) {
                this.pendingEdits[productId] = { cells: {} };
            }
            this.warehouses.forEach(w => {
                if (!this.pendingEdits[productId].cells[w.id]) {
                    this.pendingEdits[productId].cells[w.id] = { qty: '', id: null };
                }
            });
        },

        // Fetches existing stock for newly-visible products in one batched
        // call (not one request per row), and only for products not
        // already loaded from an earlier search.
        loadMissingStock() {
            const idsToLoad = this.visibleProducts
                .map(p => p.id)
                .filter(id => !this.pendingEdits[id] && !this.loadingIds[id]);

            if (!idsToLoad.length) return;

            idsToLoad.forEach(id => {
                this.loadingIds[id] = true;
                this.ensureProductState(id);
            });

            this.$wire.call('getExistingStockBatch', idsToLoad).then(result => {
                Object.entries(result || {}).forEach(([productId, stocks]) => {
                    this.ensureProductState(productId);
                    stocks.forEach(stock => {
                        this.pendingEdits[productId].cells[stock.warehouse_id] = { qty: stock.qty, id: stock.id };
                    });
                });
                idsToLoad.forEach(id => { delete this.loadingIds[id]; });
                this.sync();
            });
        },

        markTouched(productId) {
            this.touchedIds[productId] = true;
        },

        editedCount() {
            return Object.keys(this.touchedIds).length;
        },

        rowTotal(productId) {
            const state = this.pendingEdits[productId];
            if (!state) return 0;
            return this.warehouses.reduce((sum, w) => sum + (parseInt(state.cells[w.id]?.qty) || 0), 0);
        },

        // Plain @keydown + manual event.key check, deliberately not
        // Alpine's @keydown.up/.down/.enter modifiers — bulletproof and
        // easy to reason about regardless of Alpine's internal key-alias
        // resolution.
        onCellKeydown(event) {
            if (event.key === 'ArrowDown' || event.key === 'Enter') {
                event.preventDefault();
                this.navigate(event, 'down');
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.navigate(event, 'up');
            }
        },

        navigate(event, direction) {
            const el = event.target;
            const row = parseInt(el.dataset.row);
            const col = el.dataset.col;
            const targetRow = direction === 'down' ? row + 1 : row - 1;

            if (targetRow < 0 || targetRow >= this.visibleProducts.length) return;

            this.$nextTick(() => {
                // Deliberately document.* here, not this.$el — inside a
                // method invoked from a directive on the <input> itself,
                // Alpine's $el resolves to that input (the element the
                // directive is bound to), not the component's root
                // container, so this.$el.querySelectorAll() was always
                // searching a leaf node with no children and finding
                // nothing. Also not a CSS-selector lookup: CSS.escape() is
                // for bare identifiers, not values already inside
                // attribute-selector quotes — a numeric data-col (warehouse
                // ids are plain numbers) gets escaped in a way that fails
                // to match. Comparing .dataset directly sidesteps both.
                const next = Array.from(document.querySelectorAll('[data-row]'))
                    .find(node => node.dataset.row === String(targetRow) && node.dataset.col === String(col));
                if (!next) return;
                next.focus();
                if (typeof next.select === 'function') next.select();
            });
        },

        onPaste(event, rowIndex, warehouseId) {
            const text = (event.clipboardData || window.clipboardData).getData('text');
            if (!text || (!text.includes('\t') && !text.includes('\n'))) {
                return; // let the browser handle a plain single-value paste
            }

            event.preventDefault();

            const warehouseOrder = this.warehouses.map(w => w.id);
            const startColIndex = warehouseOrder.indexOf(warehouseId);
            const lines = text.replace(/\r/g, '').split('\n').filter((line, i, arr) => !(i === arr.length - 1 && line === ''));

            lines.forEach((line, rOffset) => {
                const values = line.split('\t');
                const targetProduct = this.visibleProducts[rowIndex + rOffset];
                if (!targetProduct) return;
                this.ensureProductState(targetProduct.id);
                this.markTouched(targetProduct.id);

                values.forEach((value, cOffset) => {
                    const wId = warehouseOrder[startColIndex + cOffset];
                    if (!wId) return;
                    this.pendingEdits[targetProduct.id].cells[wId].qty = value.trim();
                });
            });

            this.sync();
        },

        serializeState() {
            return JSON.stringify(this.pendingEdits);
        },

        sync() {
            this.$wire.$set(this.statePath, this.serializeState(), false);
        },
    };
}
</script>
