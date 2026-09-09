{{--
    One roster block: Player Name/Initials/Number (left, fixed) | item
    columns (middle, scrolls) | Notes/Row Total/Actions (right, fixed).

    Included twice from order-grid.blade.php — once per `$section`
    ('goalie' | 'player') — against the SAME underlying `columns`/`rows`
    Alpine arrays, filtered live via columnsFor()/sectionRows() so summary
    and total figures (which iterate the full arrays) stay naturally
    combined across both sections without any extra wiring. See the
    Goalkeeper/Player Items comment block in order-grid.blade.php for why.
--}}
<div class="flex items-stretch overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
    x-effect="columnsFor('{{ $section }}').map((c) => c.product_id).join('|'); syncHeaderHeight('{{ $section }}')">
    <!-- LEFT: Player Name / Initials / Number — fixed, never scrolls -->
    <div class="grid shrink-0 border-r border-gray-200 text-sm dark:border-gray-700"
        style="grid-template-columns: 10rem 5rem 5rem">
        <div class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 text-left font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight['{{ $section }}'] + 'px'">Player Name</div>
        <div class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 text-left font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight['{{ $section }}'] + 'px'">Initials</div>
        <div class="border-b border-gray-200 bg-gray-50 px-2 py-2 text-left font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight['{{ $section }}'] + 'px'">Number</div>

        <template x-for="(row, rowIndex) in sectionRows('{{ $section }}')" :key="row.key">
            <div style="display: contents">
                <div class="border-b border-r border-gray-100 p-1 dark:border-gray-800"
                    :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                    <input type="text" x-model="row.player_name" placeholder="Player name" autocomplete="off"
                        :name="'player_name_{{ $section }}_' + rowIndex" :id="'player_name_{{ $section }}_' + rowIndex"
                        :data-row="rowIndex" data-col="player_name" data-section="{{ $section }}"
                        @keydown.enter.prevent="navigate($event, 'down', true)"
                        @keydown.down.prevent="navigate($event, 'down')"
                        @keydown.up.prevent="navigate($event, 'up')"
                        @paste="onPaste($event, rowIndex, 'player_name')"
                        @input="row.player_name = row.player_name.toUpperCase(); sync()"
                        class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                </div>
                <div class="border-b border-r border-gray-100 p-1 dark:border-gray-800"
                    :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                    <input type="text" x-model="row.initials" placeholder="Init." autocomplete="off"
                        :name="'initials_{{ $section }}_' + rowIndex" :id="'initials_{{ $section }}_' + rowIndex"
                        :data-row="rowIndex" data-col="initials" data-section="{{ $section }}"
                        @keydown.enter.prevent="navigate($event, 'down', true)"
                        @keydown.down.prevent="navigate($event, 'down')"
                        @keydown.up.prevent="navigate($event, 'up')"
                        @paste="onPaste($event, rowIndex, 'initials')"
                        @input="row.initials = row.initials.toUpperCase(); sync()"
                        class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                </div>
                <div class="border-b border-gray-100 p-1 dark:border-gray-800"
                    :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                    <input type="text" x-model="row.number" placeholder="#" autocomplete="off"
                        :name="'number_{{ $section }}_' + rowIndex" :id="'number_{{ $section }}_' + rowIndex"
                        :data-row="rowIndex" data-col="number" data-section="{{ $section }}"
                        @keydown.enter.prevent="navigate($event, 'down', true)"
                        @keydown.down.prevent="navigate($event, 'down')"
                        @keydown.up.prevent="navigate($event, 'up')"
                        @paste="onPaste($event, rowIndex, 'number')"
                        @input="sync()"
                        class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                </div>
            </div>
        </template>

        <!-- Matches the height of the middle block's mirrored scrollbar row so the footer row below stays aligned across all three blocks. -->
        <div class="h-4 border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800" style="grid-column: span 3"></div>
        <div class="border-t border-gray-200 bg-gray-50 px-2 py-2 font-semibold dark:border-gray-700 dark:bg-gray-800" style="grid-column: span 3"
            x-text="(isPackageType() && hasGoalieItems()) ? 'Section Total' : 'Grand Total'"></div>
    </div>

    <!-- MIDDLE: item columns — the only thing that scrolls -->
    <div class="min-w-0 flex-1 overflow-x-auto"
        x-ref="hScroll_{{ $section }}" @scroll="if ($refs.hScrollMirror_{{ $section }}) $refs.hScrollMirror_{{ $section }}.scrollLeft = $event.target.scrollLeft">
        <div class="grid text-sm" :style="'grid-template-columns: repeat(' + columnsFor('{{ $section }}').length + ', 9rem)'">
            <template x-for="col in columnsFor('{{ $section }}')" :key="col.key">
                <div x-ref="itemHeaderCell_{{ $section }}" class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-1">
                        <div class="min-w-0 flex-1">
                            <p class="line-clamp-2 whitespace-normal break-words text-xs font-semibold leading-snug text-gray-900 dark:text-gray-100"
                                x-text="productName(col.product_id) || 'No item selected'"></p>
                            <input type="text" list="order-grid-products" autocomplete="off"
                                :name="'product_' + col.key" :id="'product_' + col.key"
                                :value="productName(col.product_id)"
                                @change="onProductInput($event, col)"
                                placeholder="Type to search / change…"
                                class="fi-input mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs">
                        </div>
                        <button type="button" x-show="canManageColumns()" @click="removeColumn(col.key)"
                            class="shrink-0 text-gray-400 hover:text-danger-600" title="Remove item">✕</button>
                    </div>
                    <div class="mt-1 flex items-center gap-1">
                        <span class="text-xs text-gray-500">$</span>
                        <input type="number" step="0.01" x-model.number="col.unit_price" autocomplete="off"
                            :name="'unit_price_' + col.key" :id="'unit_price_' + col.key"
                            :disabled="!canEditPrices" :title="!canEditPrices ? 'Only an admin can change pricing' : null"
                            class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs disabled:cursor-not-allowed disabled:opacity-60">
                    </div>
                    {{-- Crest artwork selection is an admin/production concern — club
                         users never see it. The col.has_club_crest / col.crest_number
                         values are still carried on the column object (prefilled from
                         the club item, default crest-on / #1) and persisted to
                         order_items, they're just not editable here for a club user. --}}
                    @if ($showCrestControls)
                    <div class="mt-1 flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                        <label class="flex items-center gap-1" title="This item carries the club crest">
                            <input type="checkbox" x-model="col.has_club_crest" @change="sync()"
                                :name="'has_club_crest_' + col.key" :id="'has_club_crest_' + col.key"
                                class="rounded border-gray-300 dark:border-gray-600">
                            <span>Crest</span>
                        </label>
                        <input type="number" min="1" step="1" x-show="col.has_club_crest" x-model.number="col.crest_number" @input="sync()"
                            :name="'crest_number_' + col.key" :id="'crest_number_' + col.key"
                            title="Which crest artwork (1, 2, 3…)"
                            class="fi-input w-12 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-xs">
                    </div>
                    @endif
                </div>
            </template>

            <template x-for="(row, rowIndex) in sectionRows('{{ $section }}')" :key="row.key">
                <div style="display: contents">
                    <template x-for="col in columnsFor('{{ $section }}')" :key="col.key">
                        <div class="border-b border-r border-gray-100 p-1 dark:border-gray-800"
                            :class="isCellChanged(row.key, col.key)
                                ? 'ring-2 ring-inset ring-orange-400 bg-orange-50 dark:bg-orange-950/40'
                                : (rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40')"
                            :title="isCellChanged(row.key, col.key) ? 'Changed in the most recent save' : null"
                            x-init="ensureCell(row, col.key)">
                            <input type="text" x-show="col.product_id" autocomplete="off"
                                :list="'order-grid-sizes-{{ $section }}-' + rowIndex + '-' + col.key"
                                :name="'size_{{ $section }}_' + rowIndex + '_' + col.key" :id="'size_{{ $section }}_' + rowIndex + '_' + col.key"
                                :value="row.cells[col.key] ? row.cells[col.key].size : ''"
                                :data-row="rowIndex" :data-col="col.key" data-section="{{ $section }}"
                                placeholder="Size…"
                                :class="(row.cells[col.key] && row.cells[col.key].invalid)
                                    ? 'fi-input w-full rounded-md text-sm border-danger-500 bg-danger-50 dark:bg-danger-950 focus:border-danger-500 focus:ring-danger-500'
                                    : 'fi-input w-full rounded-md text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900'"
                                @keydown.enter.prevent="navigate($event, 'down', true)"
                                @keydown.down.prevent="navigate($event, 'down')"
                                @keydown.up.prevent="navigate($event, 'up')"
                                @paste="onPaste($event, rowIndex, col.key)"
                                @input="onSizeTyping($event, row, col)"
                                @change="onSizeInput($event, row, col)">
                            <datalist :id="'order-grid-sizes-{{ $section }}-' + rowIndex + '-' + col.key">
                                <template x-for="size in productSizes(col)" :key="size">
                                    <option :value="size"></option>
                                </template>
                            </datalist>
                        </div>
                    </template>
                </div>
            </template>

            <!--
                A second, mirrored horizontal scrollbar sitting right
                above the footer row — dragging it (or the one at the
                bottom of this block) scrolls both together via the
                @scroll listeners on each, so it's reachable without
                scrolling all the way down a long roster first.
            -->
            <div x-show="columnsFor('{{ $section }}').length" class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800"
                :style="'grid-column: span ' + columnsFor('{{ $section }}').length"
                x-ref="hScrollMirror_{{ $section }}" @scroll="$refs.hScroll_{{ $section }}.scrollLeft = $event.target.scrollLeft">
                <div class="h-4" :style="'width: ' + (columnsFor('{{ $section }}').length * 9) + 'rem'"></div>
            </div>

            <div class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800" :style="'grid-column: span ' + columnsFor('{{ $section }}').length"></div>
        </div>
    </div>

    <!-- RIGHT: Notes / Row Total / Actions — fixed, never scrolls -->
    <div class="grid shrink-0 border-l border-gray-200 text-sm dark:border-gray-700"
        style="grid-template-columns: 10rem 6rem 40px">
        <div class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 text-left font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight['{{ $section }}'] + 'px'">Notes</div>
        <div class="border-b border-r border-gray-200 bg-gray-50 px-2 py-2 text-right font-medium dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight['{{ $section }}'] + 'px'">Row Total</div>
        <div class="border-b border-gray-200 bg-gray-50 px-2 py-2 dark:border-gray-700 dark:bg-gray-800" :style="'min-height: ' + headerHeight['{{ $section }}'] + 'px'"></div>

        <template x-for="(row, rowIndex) in sectionRows('{{ $section }}')" :key="row.key">
            <div style="display: contents">
                <div class="border-b border-r border-gray-100 p-1 dark:border-gray-800"
                    :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                    <input type="text" x-model="row.notes" placeholder="Notes" autocomplete="off"
                        :name="'notes_{{ $section }}_' + rowIndex" :id="'notes_{{ $section }}_' + rowIndex"
                        :data-row="rowIndex" data-col="notes" data-section="{{ $section }}"
                        @keydown.enter.prevent="navigate($event, 'down', true)"
                        @keydown.down.prevent="navigate($event, 'down')"
                        @keydown.up.prevent="navigate($event, 'up')"
                        @paste="onPaste($event, rowIndex, 'notes')"
                        @input="sync()"
                        class="fi-input w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
                </div>
                <div class="border-b border-r border-gray-100 p-1 text-right font-medium tabular-nums dark:border-gray-800"
                    :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'"
                    x-text="'$' + rowTotal(row).toFixed(2)"></div>
                <div class="border-b border-gray-100 p-1 text-center whitespace-nowrap dark:border-gray-800"
                    :class="rowIndex % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50/50 dark:bg-gray-800/40'">
                    <button type="button" @click="duplicateRow(row.key)" class="text-gray-400 hover:text-primary-600" title="Duplicate this row">⧉</button>
                    <button type="button" @click="removeRow(row.key)" class="text-gray-400 hover:text-danger-600" title="Remove row">🗑</button>
                </div>
            </div>
        </template>

        <!-- Matches the height of the middle block's mirrored scrollbar row so the footer row below stays aligned across all three blocks. -->
        <div class="h-4 border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800" style="grid-column: span 3"></div>

        <div class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800"></div>
        <div class="border-t border-gray-200 bg-gray-50 px-2 py-2 text-right font-semibold tabular-nums dark:border-gray-700 dark:bg-gray-800"
            x-text="'$' + sectionTotal('{{ $section }}').toFixed(2)"></div>
        <div class="border-t border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800"></div>
    </div>
</div>

<div class="flex flex-wrap items-center gap-2 mt-2">
    <button type="button" @click="addRow('{{ $section }}')"
        class="fi-btn inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
        + Add Row
    </button>

    <div class="inline-flex items-center gap-1">
        <input type="number" min="1" max="500" x-model.number="bulkAddCount.{{ $section }}" autocomplete="off"
            name="bulk_add_count_{{ $section }}" id="bulk_add_count_{{ $section }}"
            class="fi-input w-16 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm">
        <button type="button" @click="addRows('{{ $section }}', bulkAddCount.{{ $section }})"
            class="fi-btn inline-flex items-center gap-1 rounded-lg bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
            + Add Rows
        </button>
    </div>
</div>
