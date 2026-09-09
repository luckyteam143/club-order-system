<div
    wire:key="logo-stock-grid"
    wire:ignore
    x-data="logoStockGrid({
        clubs: @js($clubs),
        logoTypes: @js($logoTypes),
        warehouses: @js($warehouses),
        initialSearch: @js($initialSearch ?? ''),
    })"
    x-init="init()"
    class="fi-logo-stock-grid"
>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-3">
            <input type="text" x-ref="search" x-model="searchQuery" name="logo_stock_grid_search" id="logo_stock_grid_search"
                @input="onSearchInput()"
                placeholder="Search club name, club code, or barcode to show matching rows…"
                autocomplete="off"
                class="fi-input w-96 max-w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
            <span x-show="searching" x-cloak class="text-xs text-gray-400 dark:text-gray-500">Searching…</span>
            <button type="button" @click="addRow()"
                class="fi-btn rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                + Add Row
            </button>
        </div>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            Search brings up existing rows to edit &middot; use + Add Row for new logo stock lines &middot; nothing is saved until you hit Save.
        </p>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm border-collapse">
            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0 z-10">
                <tr>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[12rem]">Club</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium w-32">Barcode</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[10rem]">Logo Type</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium w-28">Stock Type</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[10rem]">Logo Name</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium w-20">Width</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium w-20">Height</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium w-28">Location</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[10rem]">Warehouse</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-right font-medium w-20">Position</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-right font-medium w-20">Qty</th>
                    <th class="border-b border-r border-gray-200 dark:border-gray-700 px-2 py-2 text-left font-medium min-w-[10rem]">Notes</th>
                    <th class="border-b border-gray-200 dark:border-gray-700 px-2 py-2 w-10"></th>
                </tr>
            </thead>
            <tbody
                @keydown.up.prevent="moveFocus($event.target, -1)"
                @keydown.down.prevent="moveFocus($event.target, 1)"
            >
                <template x-for="(row, rowIndex) in rows" :key="row.key">
                    <tr class="odd:bg-white even:bg-gray-50/50 dark:odd:bg-gray-900 dark:even:bg-gray-800/40">
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <select x-model.number="row.club_id" @change="onClubChange(row)"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                <option value="">—</option>
                                <template x-for="c in clubs" :key="c.id">
                                    <option :value="c.id" x-text="c.name + (c.code ? ' (' + c.code + ')' : '')"></option>
                                </template>
                            </select>
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.barcode" autocomplete="off"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <select x-model.number="row.logo_type_id"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                <option value="">—</option>
                                <template x-for="t in logoTypes" :key="t.id">
                                    <option :value="t.id" x-text="t.name"></option>
                                </template>
                            </select>
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <select x-model="row.logo_stock_type"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                <option value="logo">Logo</option>
                                <option value="numbers">Numbers</option>
                                <option value="sponsor">Sponsor</option>
                            </select>
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.logo_name" autocomplete="off"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.width" autocomplete="off"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.height" autocomplete="off"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.location" autocomplete="off"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <select x-model.number="row.warehouse_id"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                                <option value="">—</option>
                                <template x-for="w in warehouses" :key="w.id">
                                    <option :value="w.id" x-text="w.name"></option>
                                </template>
                            </select>
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" inputmode="numeric" x-model="row.position" autocomplete="off"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm text-right">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" inputmode="numeric" x-model="row.qty" autocomplete="off"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm text-right">
                        </td>
                        <td class="border-b border-r border-gray-100 dark:border-gray-800 p-1">
                            <input type="text" x-model="row.notes" autocomplete="off"
                                class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                        </td>
                        <td class="border-b border-gray-100 dark:border-gray-800 p-1 text-center">
                            <button type="button" @click="removeRow(rowIndex)" title="Remove row"
                                class="text-gray-400 hover:text-danger-600 dark:hover:text-danger-400">✕</button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2" x-show="searchQuery.trim().length < 2 && rows.length === 0">
        Type at least 2 characters above to show matching rows, or use + Add Row to start a brand new one.
    </p>
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2" x-show="searchQuery.trim().length >= 2 && rows.length === 0 && !searching">
        No matching rows.
    </p>

    <div class="mt-4 flex items-center gap-3">
        <button type="button" @click="save()" :disabled="saving || rows.length === 0"
            class="fi-btn rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50">
            <span x-show="!saving">Save Logos Stock</span>
            <span x-show="saving" x-cloak>Saving…</span>
        </button>
    </div>
</div>

<script>
function logoStockGrid(config) {
    return {
        clubs: config.clubs,
        logoTypes: config.logoTypes,
        warehouses: config.warehouses,
        searchQuery: '',
        rows: [],
        searching: false,
        saving: false,
        searchTimer: null,
        // Bumped on every new search so a slow, now-stale response can't
        // clobber the results of a more recent one typed after it.
        searchToken: 0,

        init() {
            if (config.initialSearch) {
                this.searchQuery = config.initialSearch;
                this.onSearchInput();
            }
        },

        blankRow() {
            return {
                key: 'new-' + Date.now() + '-' + Math.random(),
                id: null,
                club_id: '',
                barcode: '',
                logo_type_id: '',
                logo_stock_type: 'logo',
                location: '',
                logo_name: '',
                width: '',
                height: '',
                notes: '',
                warehouse_id: '',
                position: 0,
                qty: 0,
            };
        },

        addRow() {
            this.rows.push(this.blankRow());
        },

        removeRow(index) {
            this.rows.splice(index, 1);
        },

        // Excel-style vertical navigation: Up/Down jumps to the same
        // column in the row above/below instead of the browser's default
        // (cycling a <select>'s options, or doing nothing in a text input)
        // — delegated once on <tbody> rather than wired per field.
        moveFocus(el, direction) {
            const cell = el.closest('td');
            const row = cell?.closest('tr');

            if (!cell || !row) return;

            const cellIndex = Array.prototype.indexOf.call(row.children, cell);
            const targetRow = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
            const targetField = targetRow?.children[cellIndex]?.querySelector('input, select');

            if (!targetField) return;

            targetField.focus();
            if (targetField.tagName === 'INPUT') targetField.select();
        },

        // Only for brand-new rows (no id yet) — a row loaded via search
        // already has its real, saved position and must never be
        // reshuffled just because someone glances at/re-picks its club.
        onClubChange(row) {
            if (row.id || !row.club_id) return;

            this.$wire.call('nextPositionForClub', row.club_id).then(position => {
                row.position = position;
            });
        },

        onSearchInput() {
            clearTimeout(this.searchTimer);
            const query = this.searchQuery.trim();

            if (query.length < 2) {
                this.searching = false;
                this.rows = this.rows.filter(r => !r.id); // keep unsaved new rows
                return;
            }

            this.searching = true;
            this.searchTimer = setTimeout(() => this.runSearch(query), 300);
        },

        runSearch(query) {
            const token = ++this.searchToken;

            this.$wire.call('searchLogoStock', query).then(results => {
                if (token !== this.searchToken) return; // superseded by a newer search

                // Each row's :key includes this search's token, not just its
                // id — re-searching (e.g. after Save) otherwise reused the
                // same key as before, and Alpine kept the *old* <select>
                // elements in place instead of re-initializing them against
                // the freshly-fetched row objects, leaving Club/Logo Type/
                // Warehouse showing blank even though the row's data (and
                // its plain text inputs, which don't have this problem) was
                // correct underneath.
                const unsaved = this.rows.filter(r => !r.id);
                this.rows = [...(results || []).map(r => ({ key: 'row-' + r.id + '-' + token, ...r })), ...unsaved];
                this.searching = false;
            });
        },

        save() {
            if (!this.rows.length) return;

            this.saving = true;

            this.$wire.call('saveRows', this.rows).then(() => {
                this.saving = false;
                if (this.searchQuery.trim().length >= 2) {
                    this.runSearch(this.searchQuery.trim());
                } else {
                    this.rows = [];
                }
            }).catch(() => { this.saving = false; });
        },
    };
}
</script>
