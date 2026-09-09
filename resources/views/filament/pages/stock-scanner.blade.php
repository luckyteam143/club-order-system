<x-filament-panels::page>
    {{-- This page is used almost exclusively on a handheld scanner's small
         screen, where the "Scan Stock" page title just eats space the
         scanning UI needs. It's still present on wider (desktop) layouts. --}}
    <style>
        @media (max-width: 640px) {
            .fi-header { display: none; }
        }

        /* Same fix as the Logo Stock Scanner: this app has no Tailwind
           build of its own for custom Filament pages, so it's stuck on
           Filament's stock compiled CSS — which only ships the utility
           classes Filament's own package templates happen to use. Classes
           like bg-success-600/bg-danger-600/text-gray-900 etc. don't exist
           as CSS rules at all here, so using them silently does nothing.
           These rules reference Filament's own --{color}-{shade} CSS
           variables directly instead (always injected in <head> for every
           registered color, regardless of which utility classes compiled). */
        .pss-selected { border-color: rgb(var(--primary-600)); background-color: rgba(var(--primary-600), .1); color: rgb(var(--primary-700, var(--primary-600))); }
        :is(.dark *) .pss-selected { background-color: rgba(var(--primary-600), .15); color: rgb(var(--primary-400, var(--primary-600))); }

        .pss-mode--add-selected { background-color: rgb(var(--success-600)); border-color: rgb(var(--success-600)); color: #fff !important; }
        .pss-mode--remove-selected { background-color: rgb(var(--danger-600)); border-color: rgb(var(--danger-600)); color: #fff !important; }

        .pss-scan-card { border-color: rgba(var(--primary-600), .4); background-color: rgba(var(--primary-600), .06); }
        :is(.dark *) .pss-scan-card { border-color: rgba(var(--primary-600), .5); background-color: rgba(var(--primary-600), .12); }
        .pss-scan-label, .pss-scan-hint { color: rgb(var(--primary-700, var(--primary-600))); }
        :is(.dark *) .pss-scan-label, :is(.dark *) .pss-scan-hint { color: rgb(var(--primary-400, var(--primary-600))); }
        .pss-scan-input { border-color: rgba(var(--primary-600), .4) !important; }
        :is(.dark *) .pss-scan-input { border-color: rgba(var(--primary-600), .5) !important; }

        .pss-banner--error, .pss-banner--remove { background-color: rgb(var(--danger-600)); }
        .pss-banner--add { background-color: rgb(var(--success-600)); }
        .pss-banner--info { background-color: rgb(var(--gray-600)); }

        .pss-heading { color: rgb(var(--gray-900)); }
        :is(.dark *) .pss-heading { color: rgb(var(--gray-50)); }

        .pss-adjust-minus { border-color: rgba(var(--danger-600), .4); background-color: rgba(var(--danger-600), .08); color: rgb(var(--danger-700, var(--danger-600))); }
        .pss-adjust-minus:hover { background-color: rgba(var(--danger-600), .15); }
        :is(.dark *) .pss-adjust-minus { border-color: rgba(var(--danger-600), .5); background-color: rgba(var(--danger-600), .15); color: rgb(var(--danger-400, var(--danger-600))); }
        .pss-adjust-plus { border-color: rgba(var(--success-600), .4); background-color: rgba(var(--success-600), .08); color: rgb(var(--success-700, var(--success-600))); }
        .pss-adjust-plus:hover { background-color: rgba(var(--success-600), .15); }
        :is(.dark *) .pss-adjust-plus { border-color: rgba(var(--success-600), .5); background-color: rgba(var(--success-600), .15); color: rgb(var(--success-400, var(--success-600))); }

        .pss-delta--pos { color: rgb(var(--success-600)); }
        :is(.dark *) .pss-delta--pos { color: rgb(var(--success-400, var(--success-600))); }
        .pss-delta--neg { color: rgb(var(--danger-600)); }
        :is(.dark *) .pss-delta--neg { color: rgb(var(--danger-400, var(--danger-600))); }

        /* On-hold controls */
        .pss-hold-box { border-color: rgba(var(--warning-600), .4); background-color: rgba(var(--warning-600), .08); }
        :is(.dark *) .pss-hold-box { border-color: rgba(var(--warning-600), .5); background-color: rgba(var(--warning-600), .14); }
        .pss-hold-check-selected { border-color: rgb(var(--warning-600)); background-color: rgba(var(--warning-600), .18); color: rgb(var(--warning-700, var(--warning-600))); }
        :is(.dark *) .pss-hold-check-selected { color: rgb(var(--warning-300, var(--warning-600))); }
        .pss-hold-hint { color: rgb(var(--warning-700, var(--warning-600))); }
        :is(.dark *) .pss-hold-hint { color: rgb(var(--warning-300, var(--warning-600))); }
        .pss-hold-value { color: rgb(var(--warning-700, var(--warning-600))); }
        :is(.dark *) .pss-hold-value { color: rgb(var(--warning-300, var(--warning-600))); }
        .pss-banner--hold { background-color: rgb(var(--warning-600)); }
    </style>

    <div
        wire:key="stock-scanner"
        wire:ignore
        x-data="stockScanner({
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
                <p class="pss-heading text-lg font-bold">Select a warehouse</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Confirm which warehouse you're scanning stock in or out of.</p>
            </div>

            <div class="flex flex-col gap-2">
                <template x-for="w in warehouses" :key="w.id">
                    <button
                        type="button"
                        @click="pendingWarehouseId = w.id"
                        :class="pendingWarehouseId === w.id
                            ? 'pss-selected'
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
                    <p class="pss-heading text-base font-bold leading-tight" x-text="currentWarehouseName()"></p>
                </div>
                <button type="button" @click="openChangeWarehouse()"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                    Change
                </button>
            </div>

            {{-- Mode — one compact row, both options always side by side --}}
            <div class="flex gap-2">
                <button
                    type="button"
                    @click="mode = 'add'; focusBarcode()"
                    :class="mode === 'add'
                        ? 'pss-mode--add-selected'
                        : 'bg-white text-gray-700 border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600'"
                    class="flex-1 whitespace-nowrap rounded-lg border-2 px-2 py-3 text-center text-sm font-bold sm:text-base"
                >
                    + Add
                </button>
                <button
                    type="button"
                    @click="mode = 'remove'; focusBarcode()"
                    :class="mode === 'remove'
                        ? 'pss-mode--remove-selected'
                        : 'bg-white text-gray-700 border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600'"
                    class="flex-1 whitespace-nowrap rounded-lg border-2 px-2 py-3 text-center text-sm font-bold sm:text-base"
                >
                    − Remove
                </button>
            </div>

            <p x-show="!mode" x-cloak class="rounded-lg border border-dashed border-gray-300 px-3 py-2 text-center text-sm font-medium text-gray-500 dark:border-gray-600 dark:text-gray-400">
                Choose Add Stock or Remove Stock above to start scanning.
            </p>

            {{-- Barcode input — the hero element, always focused and ready.
                 Deliberately a plain, unrestricted text input: an earlier
                 attempt to suppress the on-screen keyboard here via
                 inputmode="none" also silently blocked scanned input on
                 devices whose barcode engine injects text through the
                 keyboard/IME layer rather than raw hardware key events —
                 scanning must always work, so the field stays fully normal
                 and any keyboard-covering issue is handled by scrolling it
                 into view on focus instead. --}}
            <div x-show="mode" x-cloak class="pss-scan-card rounded-lg border-2 p-3">
                <label class="pss-scan-label mb-1 block text-sm font-semibold">
                    <span x-show="mode === 'add'">📥 Scan to add stock</span>
                    <span x-show="mode === 'remove'">📤 Scan to remove stock</span>
                </label>
                <input
                    type="text" x-ref="barcodeInput" x-model="barcodeValue"
                    @keydown.enter.prevent="onScan()"
                    @input="scheduleAutoScan()"
                    @focus="scrollIntoView($event.target)"
                    @blur="refocusSoon()"
                    autocomplete="off" autocapitalize="off" spellcheck="false"
                    placeholder="Ready to scan…"
                    class="fi-input pss-scan-input block w-full rounded-lg py-3 text-lg font-semibold tracking-wide dark:bg-gray-900"
                >
                <p class="pss-scan-hint mt-1 text-xs">Ready for scanner input.</p>
                <div class="mt-2 flex items-center gap-2">
                    <label class="pss-scan-label text-sm whitespace-nowrap">Adjust by</label>
                    <input
                        type="number" inputmode="numeric" min="1" step="1"
                        x-model.number="stepAmount"
                        @focus="scrollIntoView($event.target)"
                        class="fi-input pss-scan-input w-16 rounded-lg py-1.5 text-base dark:bg-gray-900"
                    >
                    <span class="pss-scan-hint text-sm">unit(s) per scan</span>
                </div>
            </div>

            {{-- Feedback banner — big and impossible to miss while eyes are on the device, not the screen --}}
            <div x-show="banner" x-cloak
                 :class="'pss-banner--' + bannerKind"
                 class="rounded-lg px-3 py-2 text-center text-base font-bold text-white shadow">
                <span x-text="banner"></span>
            </div>

            {{-- Loaded product card --}}
            <div x-show="product" x-cloak class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <p class="pss-heading text-base font-semibold" x-text="product?.name"></p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    <span x-show="product?.size" x-text="'Size: ' + product?.size"></span>
                    <span x-show="product?.barcode" x-text="'  ·  Barcode: ' + product?.barcode"></span>
                </p>

                {{-- On-hold options. Both start unchecked; ticking one unticks
                     the other (they can never be on together), and ticking a
                     ticked box clears it so neither is on.
                       • Put On Hold      — a REMOVE scan/update takes the units
                                            out of stock and parks them in the
                                            on-hold reserve instead of just
                                            dropping them. No effect on an Add.
                       • Update On Hold Qty — every scan/update in this mode
                                            adds to / removes from the on-hold
                                            reserve directly, leaving the main
                                            stock quantity untouched. --}}
                <div class="pss-hold-box mt-3 rounded-lg border p-2">
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" @click="toggleHold('put')"
                            :class="holdMode === 'put'
                                ? 'pss-hold-check-selected'
                                : 'border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200'"
                            class="flex items-center gap-1.5 rounded-lg border-2 px-2 py-2 text-left text-xs font-semibold sm:text-sm">
                            <span x-text="holdMode === 'put' ? '☑' : '☐'"></span>
                            <span>Put On Hold</span>
                        </button>
                        <button type="button" @click="toggleHold('update')"
                            :class="holdMode === 'update'
                                ? 'pss-hold-check-selected'
                                : 'border-gray-300 bg-white text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200'"
                            class="flex items-center gap-1.5 rounded-lg border-2 px-2 py-2 text-left text-xs font-semibold sm:text-sm">
                            <span x-text="holdMode === 'update' ? '☑' : '☐'"></span>
                            <span>Update On Hold Qty</span>
                        </button>
                    </div>
                    <p x-show="holdMode === 'put'" x-cloak class="pss-hold-hint mt-1.5 text-xs">
                        Removing stock will move it into On Hold. Adding has no effect here.
                    </p>
                    <p x-show="holdMode === 'update'" x-cloak class="pss-hold-hint mt-1.5 text-xs">
                        Scans / updates change On Hold only — the main stock quantity is left alone.
                    </p>
                </div>

                <div class="mt-2 flex items-center justify-between gap-2">
                    <span class="text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap"
                        x-text="holdMode === 'update' ? 'On hold here' : 'Qty here'"></span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="applyDelta(-stepAmount)"
                            class="pss-adjust-minus whitespace-nowrap rounded-lg border-2 px-3 py-1.5 text-base font-bold">
                            − <span x-text="stepAmount"></span>
                        </button>
                        <span class="pss-heading w-10 text-center text-2xl font-extrabold tabular-nums"
                            x-text="(holdMode === 'update' ? (product?.qty_on_hold ?? 0) : (product?.qty ?? 0))"></span>
                        <button type="button" @click="applyDelta(stepAmount)"
                            class="pss-adjust-plus whitespace-nowrap rounded-lg border-2 px-3 py-1.5 text-base font-bold">
                            + <span x-text="stepAmount"></span>
                        </button>
                    </div>
                </div>

                {{-- Secondary read-out of the bucket not currently being edited. --}}
                <div class="mt-1 flex items-center justify-between gap-2 text-xs">
                    <span x-show="holdMode === 'update'" class="text-gray-500 dark:text-gray-400">In stock here</span>
                    <span x-show="holdMode !== 'update'" class="pss-hold-value">On hold here</span>
                    <span class="font-semibold tabular-nums"
                        :class="holdMode === 'update' ? 'text-gray-500 dark:text-gray-400' : 'pss-hold-value'"
                        x-text="(holdMode === 'update' ? (product?.qty ?? 0) : (product?.qty_on_hold ?? 0))"></span>
                </div>

                {{-- Manual entry — type a pile count (e.g. 100 pieces just picked)
                     and Update Stock adds or removes exactly that many from the
                     current total, per whichever mode (Add/Remove) is active
                     above. Always starts blank/0, never pre-filled with the
                     current stock — this is a quantity to apply, not a value to
                     overwrite. With an on-hold box ticked it applies to the
                     on-hold reserve per the rules above. --}}
                <div class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                    <input
                        type="number" inputmode="numeric" min="0" step="1"
                        x-model.number="manualQty"
                        x-ref="manualQtyInput"
                        placeholder="Pieces"
                        @focus="scrollIntoView($event.target)"
                        class="fi-input w-20 rounded-lg border-gray-300 py-2 text-base dark:border-gray-600 dark:bg-gray-900"
                    >
                    <button type="button" @click="updateStock()"
                        :disabled="!mode || !manualQty"
                        class="flex-1 rounded-lg bg-primary-600 py-2.5 text-base font-bold text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-text="updateButtonLabel()"></span>
                    </button>
                </div>
            </div>

            {{-- Recent scans this session — quick confirmation trail, most recent first --}}
            <div x-show="recentScans.length > 0" x-cloak>
                <p class="mb-1 text-sm font-medium text-gray-600 dark:text-gray-300">Recent scans</p>
                <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    <template x-for="scan in recentScans" :key="scan.key">
                        <li class="flex items-center justify-between px-3 py-1.5 text-sm">
                            <span class="flex min-w-0 items-center gap-1.5 pr-2">
                                <span x-show="scan.tag === 'hold'" x-cloak
                                    class="pss-hold-value shrink-0 rounded px-1 text-[0.65rem] font-bold uppercase tracking-wide">hold</span>
                                <span class="truncate text-gray-700 dark:text-gray-200" x-text="scan.name"></span>
                            </span>
                            <span :class="scan.delta >= 0 ? 'pss-delta--pos' : 'pss-delta--neg'" class="font-semibold tabular-nums whitespace-nowrap">
                                <span x-text="scan.delta >= 0 ? ('+' + scan.delta) : scan.delta"></span>
                                → <span x-text="scan.qty"></span>
                            </span>
                        </li>
                    </template>
                </ul>
            </div>

            <a href="{{ \App\Filament\Resources\StockResource::getUrl('index') }}"
                class="fi-link text-center text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                View full stock listing →
            </a>
        </div>
    </div>

    <script>
        function stockScanner(config) {
            return {
                warehouses: config.warehouses,
                assignedWarehouseId: config.assignedWarehouseId,
                confirmed: config.warehouseConfirmed,
                warehouseId: config.initialWarehouseId,
                pendingWarehouseId: config.initialWarehouseId,
                mode: null,
                // '' = neither on-hold box ticked, 'put' = Put On Hold,
                // 'update' = Update On Hold Qty. Only ever one at a time.
                holdMode: '',
                stepAmount: 1,
                barcodeValue: '',
                product: null,
                manualQty: 0,
                banner: '',
                bannerKind: 'info',
                bannerTimer: null,
                recentScans: [],
                lastScan: { barcode: null, time: 0 },
                autoScanTimer: null,

                init() {
                    if (this.confirmed) {
                        this.focusBarcode();
                    }
                },

                currentWarehouseName() {
                    return this.warehouses.find(w => w.id === this.warehouseId)?.name ?? '';
                },

                // Ticking a box turns the other off; ticking the box that's
                // already on clears it, so "neither" is always reachable.
                toggleHold(which) {
                    this.holdMode = this.holdMode === which ? '' : which;
                    this.focusBarcode();
                },

                updateButtonLabel() {
                    if (this.holdMode === 'update') {
                        return this.mode === 'remove' ? 'Remove From Hold' : 'Add To Hold';
                    }
                    if (this.holdMode === 'put') {
                        return this.mode === 'remove' ? 'Remove & Hold' : 'Update Stock';
                    }
                    return this.mode === 'remove' ? 'Remove Stock' : 'Update Stock';
                },

                confirmWarehouse() {
                    if (!this.pendingWarehouseId) return;

                    const warehouseChanged = this.warehouseId !== this.pendingWarehouseId;
                    this.warehouseId = this.pendingWarehouseId;
                    this.confirmed = true;

                    if (warehouseChanged && this.product) {
                        this.$wire.call('refreshForWarehouse', this.product.id, this.warehouseId).then(result => {
                            if (result) {
                                this.product = result;
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

                // Handheld scanners re-send keystrokes into whatever's
                // focused — if the user's thumb grazes something else, this
                // brings focus straight back so the next scan isn't lost.
                // But if the user deliberately tapped another field on this
                // page (e.g. the manual qty box) it's still focused when
                // this timer fires, so leave it alone instead of yanking
                // focus back to the barcode input mid-typing.
                refocusSoon() {
                    setTimeout(() => {
                        const active = document.activeElement;
                        if (active && active !== this.$refs.barcodeInput && ['INPUT', 'SELECT', 'TEXTAREA'].includes(active.tagName)) {
                            return;
                        }
                        this.focusBarcode();
                    }, 150);
                },

                // Keeps whatever the user just tapped (barcode field, step
                // amount, manual qty) above the on-screen keyboard instead
                // of letting it get covered once the keyboard slides up.
                scrollIntoView(el) {
                    setTimeout(() => el?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 250);
                },

                vibrate(pattern) {
                    if (navigator.vibrate) navigator.vibrate(pattern);
                },

                showBanner(text, kind) {
                    clearTimeout(this.bannerTimer);
                    this.banner = text;
                    this.bannerKind = ['error', 'remove', 'add', 'info', 'hold'].includes(kind) ? kind : 'info';
                    this.bannerTimer = setTimeout(() => { this.banner = ''; }, 2000);
                },

                // Some scanners (several Zebra DataWedge "Keystroke output"
                // profiles by default) inject the barcode's characters but
                // never send a trailing Enter/terminator key — the
                // @keydown.enter listener above then never fires and the
                // code just sits in the field. This is the fallback: if no
                // more keystrokes arrive for 200ms after the field last
                // changed (well past scanner keystroke speed, well short of
                // a human still typing), auto-fire the scan. Enter still
                // wins the race for scanners that do send it — this timer
                // is cleared/redundant in that case since onScan() has
                // already emptied the field by the time it would fire.
                scheduleAutoScan() {
                    clearTimeout(this.autoScanTimer);

                    if (!this.barcodeValue.trim()) return;

                    this.autoScanTimer = setTimeout(() => {
                        if (this.barcodeValue.trim()) this.onScan();
                    }, 200);
                },

                onScan() {
                    clearTimeout(this.autoScanTimer);

                    const barcode = this.barcodeValue.trim();
                    this.barcodeValue = '';

                    if (!barcode || !this.mode) return;

                    // Guards only against a scanner double-firing the same
                    // code within the same trigger pull (typically well
                    // under 300ms on cheap Bluetooth HID scanners) — kept
                    // short on purpose so a deliberate quick second scan of
                    // the same item (which is exactly how you commit an
                    // adjustment below) is never swallowed.
                    const now = Date.now();
                    if (barcode === this.lastScan.barcode && (now - this.lastScan.time) < 300) {
                        return;
                    }
                    this.lastScan = { barcode, time: now };

                    const delta = this.mode === 'add' ? this.stepAmount : -this.stepAmount;
                    const currentProductId = this.product?.id ?? null;

                    // One round trip that both looks the barcode up and,
                    // when it matches the item already on screen, commits
                    // the adjustment — half the latency of the old
                    // lookup-then-adjust pair of calls.
                    this.$wire.call('scanBarcode', barcode, this.warehouseId, currentProductId, delta, this.holdMode || 'none').then(result => {
                        if (!result) {
                            this.product = null;
                            this.vibrate([80, 60, 80]);
                            this.showBanner('No product found for that barcode', 'error');
                            this.focusBarcode();
                            return;
                        }

                        this.product = result;

                        if (result.adjusted) {
                            this.vibrate(40);
                            this.commitFeedback(result, delta);
                        } else {
                            // First scan of this item — just confirms what
                            // it is and its current stock. Scan it again to
                            // actually add/remove.
                            this.vibrate(20);
                            if (this.holdMode === 'update') {
                                this.showBanner(
                                    'On hold: ' + result.qty_on_hold + ' — scan again to ' + (this.mode === 'add' ? 'add to' : 'remove from') + ' hold',
                                    'info',
                                );
                            } else {
                                this.showBanner(
                                    'Current stock: ' + result.qty + ' (on hold: ' + result.qty_on_hold + ') — scan again to ' + (this.mode === 'add' ? 'add' : 'remove'),
                                    'info',
                                );
                            }
                        }

                        this.focusBarcode();
                    });
                },

                applyDelta(delta, name = null) {
                    if (!this.product || !delta) return;

                    const label = name ?? this.product.name;

                    this.$wire.call('adjustStock', this.product.id, delta, this.warehouseId, this.holdMode || 'none').then(result => {
                        if (!result) return;
                        this.product = result;
                        this.vibrate(40);
                        this.commitFeedback(result, delta, label);
                        this.focusBarcode();
                    });
                },

                // Manual pile-count entry — e.g. 100 pieces just picked or
                // received. Applies as a relative add/remove on top of the
                // current total (per the active mode), not an overwrite.
                updateStock() {
                    if (!this.product || !this.mode) return;

                    const amount = Math.max(0, parseInt(this.manualQty) || 0);
                    if (!amount) return;

                    const delta = this.mode === 'add' ? amount : -amount;

                    this.$wire.call('adjustStock', this.product.id, delta, this.warehouseId, this.holdMode || 'none').then(result => {
                        if (!result) return;
                        this.product = result;
                        this.manualQty = 0;
                        this.vibrate(40);
                        this.commitFeedback(result, delta);
                        this.focusBarcode();
                    });
                },

                // Post-movement banner + recent-scan row, worded for whichever
                // bucket the units actually landed in (stock, or on-hold).
                commitFeedback(result, delta, name = null) {
                    const label = name ?? result.name;

                    if (this.holdMode === 'update') {
                        this.pushRecent(label, delta, result.qty_on_hold, 'hold');
                        this.showBanner(
                            (delta >= 0 ? '+' : '') + delta + ' on hold — now ' + result.qty_on_hold,
                            'hold',
                        );
                        return;
                    }

                    if (this.holdMode === 'put' && delta < 0) {
                        this.pushRecent(label, delta, result.qty, 'hold');
                        this.showBanner(
                            delta + ' to hold — stock ' + result.qty + ', on hold ' + result.qty_on_hold,
                            'hold',
                        );
                        return;
                    }

                    this.pushRecent(label, delta, result.qty);
                    this.showBanner(
                        (delta >= 0 ? '+' : '') + delta + ' — new qty: ' + result.qty,
                        delta >= 0 ? 'add' : 'remove',
                    );
                },

                pushRecent(name, delta, qty, tag = null) {
                    this.recentScans.unshift({ key: Date.now() + '-' + Math.random(), name, delta, qty, tag });
                    this.recentScans = this.recentScans.slice(0, 10);
                },
            };
        }
    </script>
</x-filament-panels::page>
