{{-- Mobile card layout for the Stock listing.

     On desktop the table is one row per product with a column per warehouse.
     Below ~768px that row scrolls off the side and you can't see a product's
     stock across warehouses at a glance, so here we collapse each row into a
     card: the product name becomes the card heading and every other cell
     (each warehouse's editable quantity, the total, etc.) stacks underneath
     as a "Label: value" line. Labels come from the data-label / the heading
     from the data-mobile-heading cell attributes set in StockResource. --}}
<style>
    @media (max-width: 768px) {
        .fi-ta-content { overflow-x: visible !important; padding: 0.5rem; }

        .fi-ta-content .fi-ta-table,
        .fi-ta-content .fi-ta-table tbody { display: block !important; width: 100%; }

        .fi-ta-content .fi-ta-table thead { display: none !important; }

        .fi-ta-content .fi-ta-table tbody { white-space: normal !important; }

        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row {
            display: block !important;
            margin-bottom: 0.625rem;
            padding: 0.625rem 0.875rem;
            border: 1px solid rgb(228 228 231);
            border-radius: 0.75rem;
            background: rgb(255 255 255);
        }
        .dark .fi-ta-content .fi-ta-table tbody tr.fi-ta-row {
            border-color: rgb(63 63 70);
            background: rgb(24 24 27);
        }

        /* Generic cell -> "label ......... value" line */
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td {
            display: flex !important;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            width: 100%;
            padding: 0.3rem 0 !important;
            border: 0 !important;
        }
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td .fi-ta-col-wrp,
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td .fi-ta-text {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
        /* let the value side take the remaining width and sit to the right */
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-label] > .fi-ta-col-wrp {
            flex: 1 1 auto;
            min-width: 0;
        }
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-label] > .fi-ta-col-wrp,
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-label] > .fi-ta-col-wrp > * {
            justify-content: flex-end !important;
            text-align: right;
        }

        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-label]::before {
            content: attr(data-label);
            flex: 0 0 auto;
            font-size: 0.8125rem;
            font-weight: 500;
            color: rgb(113 113 122);
        }
        .dark .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-label]::before {
            color: rgb(161 161 170);
        }

        /* Product name: full-width card heading */
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-mobile-heading] {
            display: block !important;
            margin-bottom: 0.375rem;
            padding-bottom: 0.5rem !important;
            border-bottom: 1px solid rgb(228 228 231) !important;
            font-weight: 600;
            font-size: 0.9rem;
            line-height: 1.3;
        }
        .dark .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-mobile-heading] {
            border-bottom-color: rgb(63 63 70) !important;
        }
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-mobile-heading] .fi-ta-text { font-weight: 600; }

        /* Warehouse quantity input: compact, right-aligned */
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-label] input[type="number"] {
            width: 5.5rem;
            text-align: right;
        }
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td[data-label] .fi-ta-text {
            justify-content: flex-end;
            text-align: right;
        }

        /* Row actions: right-aligned at the foot of the card */
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td.fi-ta-actions-cell {
            justify-content: flex-end;
            padding-top: 0.5rem !important;
        }
        .fi-ta-content .fi-ta-table tbody tr.fi-ta-row > td.fi-ta-actions-cell > div { padding: 0 !important; }
    }
</style>

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
