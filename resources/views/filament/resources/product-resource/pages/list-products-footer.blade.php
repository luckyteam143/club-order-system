<div x-data="{}" x-on:start-inline-edit.window="Alpine.store('productsEdit').startEditing($event.detail.ids)">
    <div
        x-show="Alpine.store('productsEdit').hasChanges()"
        x-cloak
        class="sticky bottom-4 z-20 flex items-center justify-between gap-4 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 shadow-lg dark:border-primary-800 dark:bg-primary-950"
    >
        <p class="text-sm text-primary-800 dark:text-primary-200">
            <span x-text="Object.keys(Alpine.store('productsEdit').editing).length"></span> row(s) being edited &mdash; use ↑ / ↓ to move between rows in a column.
        </p>
        <div class="flex items-center gap-2">
            <button
                type="button"
                x-on:click="Alpine.store('productsEdit').discardAll()"
                class="fi-btn rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                Discard
            </button>
            <button
                type="button"
                x-bind:disabled="Alpine.store('productsEdit').saving"
                x-on:click="
                    Alpine.store('productsEdit').saving = true;
                    $wire.call('saveInlineEdits', Alpine.store('productsEdit').buildPayload()).then(() => {
                        Alpine.store('productsEdit').editing = {};
                        Alpine.store('productsEdit').saving = false;
                    }).catch(() => { Alpine.store('productsEdit').saving = false; });
                "
                class="fi-btn rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-50"
            >
                <span x-show="!Alpine.store('productsEdit').saving">Save Changes</span>
                <span x-show="Alpine.store('productsEdit').saving" x-cloak>Saving&hellip;</span>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    if (Alpine.store('productsEdit')) {
        return;
    }

    Alpine.store('productsEdit', {
        editing: {},
        pending: {},
        original: {},
        saving: false,

        isEditing(id) {
            return !!this.editing[id];
        },

        // Called for one row (per-row "Edit" action) or many at once (the
        // "Inline Edit" bulk action) — both dispatch the same browser event.
        startEditing(ids) {
            ids.forEach(id => {
                if (!this.pending[id]) this.pending[id] = {};
                if (!this.original[id]) this.original[id] = {};

                document.querySelectorAll('[data-record-id="' + id + '"][data-col]').forEach(el => {
                    const field = el.dataset.col;
                    if (this.original[id][field] === undefined) {
                        this.original[id][field] = this.pending[id][field];
                    }
                });

                this.editing[id] = true;
            });
        },

        cancelEditing(ids) {
            ids.forEach(id => {
                if (this.original[id]) {
                    Object.keys(this.original[id]).forEach(field => {
                        this.pending[id][field] = this.original[id][field];
                    });
                }
                delete this.editing[id];
            });
        },

        discardAll() {
            this.cancelEditing(Object.keys(this.editing));
        },

        hasChanges() {
            return Object.keys(this.editing).length > 0;
        },

        buildPayload() {
            const payload = {};
            Object.keys(this.editing).forEach(id => {
                payload[id] = this.pending[id];
            });
            return payload;
        },

        // Plain @keydown + manual event.key check, and document.* (not
        // this.$el, which is only the directive's own element) — same
        // pattern proven out on the Order and Bulk Stock grids.
        onKeydown(event) {
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
                return;
            }
            event.preventDefault();

            const field = event.target.dataset.col;
            const cells = Array.from(document.querySelectorAll('[data-col="' + field + '"]'))
                .filter(el => el.offsetParent !== null);
            const idx = cells.indexOf(event.target);
            if (idx === -1) return;

            const next = cells[idx + (event.key === 'ArrowDown' ? 1 : -1)];
            if (!next) return;

            next.focus();
            if (typeof next.select === 'function') next.select();
        },
    });
});
</script>
