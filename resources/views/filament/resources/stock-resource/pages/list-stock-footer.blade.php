<div
    x-data="{}"
    x-show="Alpine.store('stockEdit').hasChanges()"
    x-cloak
    class="sticky bottom-4 z-20 flex items-center justify-between gap-4 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 shadow-lg dark:border-primary-800 dark:bg-primary-950"
>
    <p class="text-sm text-primary-800 dark:text-primary-200">
        <span x-text="Object.keys(Alpine.store('stockEdit').touched).length"></span> unsaved cell change(s) &mdash; use ↑ / ↓ to move between rows in a column.
    </p>
    <div class="flex items-center gap-2">
        <button
            type="button"
            x-on:click="Alpine.store('stockEdit').discard()"
            class="fi-btn rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
        >
            Discard
        </button>
        <button
            type="button"
            x-bind:disabled="Alpine.store('stockEdit').saving"
            x-on:click="
                Alpine.store('stockEdit').saving = true;
                $wire.call('saveInlineEdits', Alpine.store('stockEdit').buildPayload()).then(() => {
                    Alpine.store('stockEdit').touched = {};
                    Alpine.store('stockEdit').saving = false;
                }).catch(() => { Alpine.store('stockEdit').saving = false; });
            "
            class="fi-btn rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500 disabled:opacity-50"
        >
            <span x-show="!Alpine.store('stockEdit').saving">Save Changes</span>
            <span x-show="Alpine.store('stockEdit').saving" x-cloak>Saving&hellip;</span>
        </button>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    if (Alpine.store('stockEdit')) {
        return;
    }

    Alpine.store('stockEdit', {
        pending: {},
        original: {},
        touched: {},
        saving: false,

        touch(id, field) {
            this.touched[id + ':' + field] = true;
        },

        hasChanges() {
            return Object.keys(this.touched).length > 0;
        },

        buildPayload() {
            const payload = {};
            Object.keys(this.touched).forEach(key => {
                const sep = key.indexOf(':');
                const id = key.slice(0, sep);
                const field = key.slice(sep + 1);
                if (!payload[id]) payload[id] = {};
                payload[id][field] = this.pending[id][field];
            });
            return payload;
        },

        discard() {
            Object.keys(this.touched).forEach(key => {
                const sep = key.indexOf(':');
                const id = key.slice(0, sep);
                const field = key.slice(sep + 1);
                if (this.pending[id]) {
                    this.pending[id][field] = this.original[key];
                }
            });
            this.touched = {};
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
