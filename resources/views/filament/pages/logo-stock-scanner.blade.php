<x-filament-panels::page>
    {{-- Mirrors the Product Stock Scanner: mostly used on a handheld device,
         so the page title is hidden on small screens to save space. --}}
    <style>
        @media (max-width: 640px) {
            .fi-header { display: none; }
        }

        /* This app has no Tailwind build of its own for custom Filament
           pages — it ships Filament's stock compiled CSS, which only
           includes the utility classes Filament's own package templates
           happen to use. Classes like bg-success-600/bg-danger-600 (and
           several primary/gray shades) simply don't exist as CSS rules in
           that bundle, so using them here silently does nothing — this was
           the actual cause of "the toggle doesn't highlight" and similar.
           Fix: hand-written rules below reference Filament's own
           --{color}-{shade} CSS custom properties directly (these ARE
           always injected in <head> for every registered color, regardless
           of which utility classes got compiled) instead of depending on
           Tailwind utility classes that may or may not exist. */
        .lss-selected { border-color: rgb(var(--primary-600)); background-color: rgba(var(--primary-600), .1); color: rgb(var(--primary-700, var(--primary-600))); }
        :is(.dark *) .lss-selected { background-color: rgba(var(--primary-600), .15); color: rgb(var(--primary-400, var(--primary-600))); }

        .lss-scan-card { border-color: rgba(var(--primary-600), .4); background-color: rgba(var(--primary-600), .06); }
        :is(.dark *) .lss-scan-card { border-color: rgba(var(--primary-600), .5); background-color: rgba(var(--primary-600), .12); }
        .lss-scan-label, .lss-scan-hint { color: rgb(var(--primary-700, var(--primary-600))); }
        :is(.dark *) .lss-scan-label, :is(.dark *) .lss-scan-hint { color: rgb(var(--primary-400, var(--primary-600))); }
        .lss-scan-input { border-color: rgba(var(--primary-600), .4) !important; }
        :is(.dark *) .lss-scan-input { border-color: rgba(var(--primary-600), .5) !important; }

        .lss-banner--error, .lss-banner--remove { background-color: rgb(var(--danger-600)); }
        .lss-banner--add { background-color: rgb(var(--success-600)); }
        .lss-banner--info { background-color: rgb(var(--gray-600)); }

        .lss-heading { color: rgb(var(--gray-900)); }
        :is(.dark *) .lss-heading { color: rgb(var(--gray-50)); }

        .lss-delta--add { color: rgb(var(--success-600)); }
        :is(.dark *) .lss-delta--add { color: rgb(var(--success-400, var(--success-600))); }
        .lss-delta--remove { color: rgb(var(--danger-600)); }
        :is(.dark *) .lss-delta--remove { color: rgb(var(--danger-400, var(--danger-600))); }

        .lss-toggle-btn { color: rgb(var(--gray-300)); }
        :is(.dark *) .lss-toggle-btn { color: rgb(var(--gray-600)); }
        .lss-toggle-btn--add-selected { background-color: rgb(var(--success-600)); color: #fff !important; }
        .lss-toggle-btn--remove-selected { background-color: rgb(var(--danger-600)); color: #fff !important; }

        /* w-12/py-1.5/tabular-nums are ALSO missing from the compiled CSS —
           this isn't only a color problem, plain sizing/spacing utilities
           are just as likely to be silently absent. Fixed width here in
           plain CSS so it's not gambling on a Tailwind class existing. */
        .lss-amount-box { width: 100px !important; padding-top: 6px; padding-bottom: 6px; font-variant-numeric: tabular-nums; }
        .lss-amount-input { border-color: rgb(var(--gray-300)) !important; color: rgb(var(--gray-900)) !important; }
        :is(.dark *) .lss-amount-input { border-color: rgb(var(--gray-600)) !important; color: rgb(var(--gray-50)) !important; }
        .lss-amount-input--active { border-color: rgb(var(--primary-600)) !important; color: rgb(var(--primary-600)) !important; }
        :is(.dark *) .lss-amount-input--active { border-color: rgb(var(--primary-600)) !important; color: rgb(var(--primary-400, var(--primary-600))) !important; }
    </style>

    <div
        wire:key="logo-stock-scanner"
        wire:ignore
        x-data="logoStockScanner({
            warehouses: @js($warehouses),
            initialWarehouseId: @js($initialWarehouseId),
            assignedWarehouseId: @js($assignedWarehouseId),
            warehouseConfirmed: @js($warehouseConfirmed),
        })"
        x-init="init()"
        class="mx-auto flex w-full max-w-md flex-col gap-3"
    >
        {{-- STEP 1 — confirm a warehouse before anything else is usable. Skipped automatically when the account already has a default warehouse. --}}
        <div x-show="!confirmed" x-cloak class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div>
                <p class="lss-heading text-lg font-bold">Select a warehouse</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Confirm which warehouse you're scanning logos stock in or out of.</p>
            </div>

            <div class="flex flex-col gap-2">
                <template x-for="w in warehouses" :key="w.id">
                    <button
                        type="button"
                        @click="pendingWarehouseId = w.id"
                        :class="pendingWarehouseId === w.id
                            ? 'lss-selected'
                            : 'border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200'"
                        class="flex items-center justify-between rounded-lg border-2 px-3 py-3 text-left text-base font-semibold"
                    >
                        <span x-text="w.name"></span>
                        <span x-show="pendingWarehouseId === w.id" class="text-primary-600 dark:text-primary-400">✓</span>
                    </button>
                </template>
            </div>

            <button
                type="button"
                @click="confirmWarehouse()"
                :disabled="!pendingWarehouseId"
                class="w-full rounded-lg bg-primary-600 py-3 text-base font-bold text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Confirm &amp; Start Scanning
            </button>
        </div>

        {{-- STEP 2 — scanning screen --}}
        <div x-show="confirmed" x-cloak class="flex flex-col gap-2">
            {{-- Current warehouse — compact, always visible, tap to change --}}
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Warehouse</p>
                    <p class="lss-heading text-base font-bold leading-tight" x-text="currentWarehouseName()"></p>
                </div>
                <button type="button" @click="openChangeWarehouse()"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                    Change
                </button>
            </div>

            {{-- Barcode input — same deliberately-unrestricted text input as the Product Stock Scanner. --}}
            <div class="lss-scan-card rounded-lg border-2 p-3">
                <label class="lss-scan-label mb-1 block text-sm font-semibold">
                    📥 Scan a barcode, or type a Club Code
                </label>
                <input
                    type="text" x-ref="barcodeInput" x-model="barcodeValue"
                    @keydown.enter.prevent="onScan()"
                    @focus="scrollIntoView($event.target)"
                    @blur="refocusSoon()"
                    autocomplete="off" autocapitalize="off" spellcheck="false"
                    placeholder="Ready to scan…"
                    class="fi-input lss-scan-input block w-full rounded-lg py-3 text-lg font-semibold tracking-wide dark:bg-gray-900"
                >
                <p class="lss-scan-hint mt-1 text-xs">Ready for scanner input — a club's Code works too if there's no barcode handy.</p>
            </div>

            {{-- Feedback banner --}}
            <div x-show="banner" x-cloak
                 :class="'lss-banner--' + bannerKind"
                 class="rounded-lg px-3 py-2 text-center text-base font-bold text-white shadow">
                <span x-text="banner"></span>
            </div>

            {{-- Loaded club's logo rows --}}
            <div x-show="club" x-cloak class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="lss-heading text-base font-semibold" x-text="club?.name"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400" x-show="club?.code" x-text="'Club Code: ' + club?.code"></p>

                {{-- Stock Type filter — display-only, doesn't affect what's loaded or what "Update" commits. --}}
                <div class="mt-2 flex gap-1.5">
                    <template x-for="option in [{ key: 'all', label: 'All' }, { key: 'logo', label: 'Logo' }, { key: 'numbers', label: 'Numbers' }]" :key="option.key">
                        <button
                            type="button"
                            @click="stockTypeFilter = option.key"
                            :class="stockTypeFilter === option.key
                                ? 'border-primary-600 bg-primary-600 text-white'
                                : 'border-gray-300 bg-white text-gray-600 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300'"
                            class="rounded-full border-2 px-3 py-1 text-xs font-bold"
                            x-text="option.label"
                        ></button>
                    </template>
                </div>

                <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-for="row in visibleRows()" :key="row.id">
                        <li class="flex items-center justify-between gap-1.5 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="lss-heading truncate text-sm font-semibold"
                                    x-text="row.logo_type ?? row.logo_name"></p>
                                <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                                    <span x-text="row.logo_name"></span>
                                    <span x-text="' · ' + (row.stock_type === 'numbers' ? 'Numbers' : 'Logo')"></span>
                                    <span x-show="row.size" x-text="' · Size: ' + row.size"></span>
                                    <span x-show="row.location" x-text="' · Box: ' + row.location"></span>
                                </p>
                                <p class="text-[11px] font-semibold tabular-nums text-gray-400 dark:text-gray-500">
                                    <span x-text="'Qty: ' + row.qty"></span>
                                    <span x-show="row.amount > 0"
                                        :class="row.mode === 'remove' ? 'lss-delta--remove' : 'lss-delta--add'"
                                        x-text="' → ' + computedQty(row)"></span>
                                </p>
                            </div>

                            {{-- Add/Remove + amount, both per row (no page-wide mode anymore) — starts
                                 at 0 (no change), typed in directly rather than tapped (this is a
                                 scan-once-then-update-manually workflow). --}}
                            <div class="flex shrink-0 items-center gap-1">
                                <div class="flex overflow-hidden rounded-md border-2 border-gray-300 dark:border-gray-600">
                                    <button type="button" @click="row.mode = 'add'"
                                        :class="row.mode === 'add' ? 'lss-toggle-btn--add-selected' : 'lss-toggle-btn bg-white dark:bg-gray-900'"
                                        class="px-2 py-2 text-base font-bold leading-none hover:bg-gray-50 dark:hover:bg-gray-800">+</button>
                                    <button type="button" @click="row.mode = 'remove'"
                                        :class="row.mode === 'remove' ? 'lss-toggle-btn--remove-selected' : 'lss-toggle-btn bg-white dark:bg-gray-900'"
                                        class="border-l-2 border-gray-300 px-2 py-2 text-base font-bold leading-none hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800">−</button>
                                </div>
                                <input
                                    type="number" inputmode="numeric" min="0" step="1" placeholder="0"
                                    x-model.number="row.amount"
                                    @change="sanitizeRowAmount(row)"
                                    @focus="scrollIntoView($event.target); $event.target.select()"
                                    class="fi-input lss-amount-box rounded-md border-2 px-1 text-center text-base font-extrabold dark:bg-gray-900"
                                    :class="row.amount > 0 ? 'lss-amount-input--active' : 'lss-amount-input'"
                                >
                            </div>
                        </li>
                    </template>
                </ul>

                <p x-show="rows.length === 0" class="py-2 text-sm text-gray-500 dark:text-gray-400">
                    No logo stock lines for this club in this warehouse.
                </p>
                <p x-show="rows.length > 0 && visibleRows().length === 0" class="py-2 text-sm text-gray-500 dark:text-gray-400">
                    No <span x-text="stockTypeFilter"></span> lines for this club in this warehouse.
                </p>

                <button type="button" @click="update()"
                    :disabled="!hasPendingChanges() || updating"
                    class="mt-3 w-full rounded-lg bg-primary-600 py-2.5 text-base font-bold text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50">
                    <span x-show="!updating">Update</span>
                    <span x-show="updating" x-cloak>Updating…</span>
                </button>
            </div>

            {{-- Recent updates this session — quick confirmation trail, most recent first (mirrors the Product Stock Scanner's "Recent scans") --}}
            <div x-show="recentScans.length > 0" x-cloak>
                <p class="mb-1 text-sm font-medium text-gray-600 dark:text-gray-300">Recent updates</p>
                <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    <template x-for="scan in recentScans" :key="scan.key">
                        <li class="flex items-center justify-between px-3 py-1.5 text-sm">
                            <span class="truncate pr-2 text-gray-700 dark:text-gray-200" x-text="scan.name"></span>
                            <span :class="scan.delta >= 0 ? 'lss-delta--add' : 'lss-delta--remove'" class="font-semibold tabular-nums whitespace-nowrap">
                                <span x-text="scan.delta >= 0 ? ('+' + scan.delta) : scan.delta"></span>
                                → <span x-text="scan.qty"></span>
                            </span>
                        </li>
                    </template>
                </ul>
            </div>

            <a href="{{ \App\Filament\Resources\LogoStockResource::getUrl('index') }}"
                class="fi-link text-center text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                View full logos stock listing →
            </a>
        </div>
    </div>

    <script>
        function logoStockScanner(config) {
            return {
                warehouses: config.warehouses,
                assignedWarehouseId: config.assignedWarehouseId,
                confirmed: config.warehouseConfirmed,
                warehouseId: config.initialWarehouseId,
                pendingWarehouseId: config.initialWarehouseId,
                barcodeValue: '',
                club: null,
                rows: [],
                stockTypeFilter: 'all',
                updating: false,
                banner: '',
                bannerKind: 'info',
                bannerTimer: null,
                recentScans: [],
                lastScan: { barcode: null, time: 0 },

                init() {
                    if (this.confirmed) {
                        this.focusBarcode();
                    }
                },

                currentWarehouseName() {
                    return this.warehouses.find(w => w.id === this.warehouseId)?.name ?? '';
                },

                confirmWarehouse() {
                    if (!this.pendingWarehouseId) return;

                    const warehouseChanged = this.warehouseId !== this.pendingWarehouseId;
                    this.warehouseId = this.pendingWarehouseId;
                    this.confirmed = true;

                    if (warehouseChanged && this.club) {
                        this.$wire.call('refreshForWarehouse', this.club.id, this.warehouseId).then(result => {
                            if (result) {
                                this.club = result.club;
                                this.rows = result.rows.map(r => ({ ...r, amount: 0, mode: 'add' }));
                            }
                        });
                    }

                    this.focusBarcode();
                },

                openChangeWarehouse() {
                    this.pendingWarehouseId = this.warehouseId;
                    this.confirmed = false;
                },

                focusBarcode() {
                    this.$nextTick(() => this.$refs.barcodeInput?.focus());
                },

                refocusSoon() {
                    setTimeout(() => {
                        const active = document.activeElement;
                        if (active && active !== this.$refs.barcodeInput && ['INPUT', 'SELECT', 'TEXTAREA'].includes(active.tagName)) {
                            return;
                        }
                        this.focusBarcode();
                    }, 150);
                },

                scrollIntoView(el) {
                    setTimeout(() => el?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 250);
                },

                vibrate(pattern) {
                    if (navigator.vibrate) navigator.vibrate(pattern);
                },

                showBanner(text, kind) {
                    clearTimeout(this.bannerTimer);
                    this.banner = text;
                    this.bannerKind = ['error', 'remove', 'add', 'info'].includes(kind) ? kind : 'info';
                    this.bannerTimer = setTimeout(() => { this.banner = ''; }, 2500);
                },

                onScan() {
                    const barcode = this.barcodeValue.trim();
                    this.barcodeValue = '';

                    if (!barcode) return;

                    const now = Date.now();
                    if (barcode === this.lastScan.barcode && (now - this.lastScan.time) < 300) {
                        return;
                    }
                    this.lastScan = { barcode, time: now };

                    this.$wire.call('scanBarcode', barcode, this.warehouseId).then(result => {
                        if (!result) {
                            this.club = null;
                            this.rows = [];
                            this.vibrate([80, 60, 80]);
                            this.showBanner('No club found for that barcode in this warehouse', 'error');
                            this.focusBarcode();
                            return;
                        }

                        this.club = result.club;
                        this.rows = result.rows.map(r => ({ ...r, amount: 0, mode: 'add' }));
                        this.vibrate(20);
                        this.showBanner('Loaded ' + this.rows.length + ' logo(s) for ' + this.club.name, 'info');
                        this.focusBarcode();
                    });
                },

                // "amount" is how much to add or remove — always >= 0, never
                // saved until "Update" is tapped. Its effect is derived live
                // via computedQty() using that row's own Add/Remove mode (no
                // page-wide mode anymore — each row picks its own), so
                // flipping a row's mode after typing re-signs its amount.
                sanitizeRowAmount(row) {
                    row.amount = Math.max(0, Math.floor(Number(row.amount) || 0));
                },

                computedQty(row) {
                    return row.mode === 'remove'
                        ? Math.max(0, row.qty - row.amount)
                        : row.qty + row.amount;
                },

                hasPendingChanges() {
                    return this.rows.some(r => r.amount > 0);
                },

                // Display-only — filters which rows are shown, but "Update"
                // still commits any pending change on every row regardless
                // of the current filter, so switching filters mid-session
                // never drops an unsaved entry.
                visibleRows() {
                    if (this.stockTypeFilter === 'all') return this.rows;
                    return this.rows.filter(r => (r.stock_type ?? 'logo') === this.stockTypeFilter);
                },

                pushRecent(name, delta, qty) {
                    this.recentScans.unshift({ key: Date.now() + '-' + Math.random(), name, delta, qty });
                    this.recentScans = this.recentScans.slice(0, 10);
                },

                update() {
                    if (!this.club || !this.hasPendingChanges()) return;

                    const updates = {};
                    const changedRows = this.rows.filter(r => r.amount > 0);
                    changedRows.forEach(r => { updates[r.id] = this.computedQty(r); });

                    // Banner color reflects the batch: all-add is green,
                    // all-remove is red, a mix of both is neutral gray.
                    const modes = new Set(changedRows.map(r => r.mode));
                    const kind = modes.size === 1 ? modes.values().next().value : 'info';

                    this.updating = true;

                    this.$wire.call('updateLogoStock', this.club.id, this.warehouseId, updates).then(result => {
                        this.updating = false;
                        if (!result) return;

                        const clubName = this.club.name;

                        changedRows.forEach(r => {
                            const delta = r.mode === 'remove' ? -r.amount : r.amount;
                            this.pushRecent(clubName + ' — ' + (r.logo_type ?? r.logo_name), delta, this.computedQty(r));
                        });

                        this.club = result.club;
                        this.rows = result.rows.map(r => ({ ...r, amount: 0, mode: 'add' }));
                        this.vibrate(40);
                        this.showBanner(Object.keys(updates).length + ' row(s) updated', kind);
                        this.focusBarcode();
                    }).catch(() => { this.updating = false; });
                },
            };
        }
    </script>
</x-filament-panels::page>
