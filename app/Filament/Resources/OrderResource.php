<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Club;
use App\Models\ClubTeam;
use App\Models\Embellishment;
use App\Models\EmbellishmentPosition;
use App\Models\Order;
use App\Models\OrderItemCell;
use App\Models\Package;
use App\Models\PackageProduct;
use App\Models\Product;
use App\Models\SponsorLogo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Orders';
    protected static ?int $navigationSort = 1;

    public const STATUSES = [
        'draft'              => 'Draft',
        'submitted'          => 'Submitted',
        'pending'            => 'Pending',
        'received'           => 'Received',
        'in_production'      => 'In Production',
        'partially_ready'    => 'Partially Ready',
        'partially_picked'   => 'Partially Picked',
        'partially_shipped'  => 'Partially Shipped',
        'shipped'            => 'Shipped',
        'completed'          => 'Completed',
        'cancelled'          => 'Cancelled',
        'admin_edit'         => 'Admin Edit',
        'forecast_submitted' => 'Forecast Submitted',
    ];

    // Statuses a club user may filter their own orders by on the club panel —
    // everything except the internal-only "Admin Edit" state.
    public const CLUB_FILTERABLE_STATUSES = [
        'draft', 'submitted', 'pending', 'received', 'in_production',
        'partially_ready', 'partially_picked', 'partially_shipped',
        'shipped', 'completed', 'cancelled', 'forecast_submitted',
    ];

    // Fulfillment states that only make sense once an order is out of
    // draft — hidden from the status dropdown until then, so a draft can't
    // be jumped straight to e.g. "Shipped".
    public const POST_SUBMIT_ONLY_STATUSES = [
        'received', 'in_production', 'partially_ready', 'partially_picked', 'partially_shipped', 'shipped', 'completed',
    ];

    // Internal warehouse-picking state — deliberately separate from
    // `status` above (which already has a club-visible `partially_picked`
    // value) so picking progress can stay hidden from club users entirely.
    public const PICKING_STATUSES = [
        'not_picked'        => 'Not Picked',
        'partially_picked'  => 'Partially Picked',
        'picked'            => 'Picked',
    ];

    public const PICKING_STATUS_COLORS = [
        'not_picked'       => 'gray',
        'partially_picked' => 'warning',
        'picked'           => 'success',
    ];

    public const ORDER_KINDS = [
        'standard' => 'Standard Order',
        'bulk'     => 'Bulk Order',
        'forecast' => 'Forecast',
    ];

    public const STATUS_COLORS = [
        'draft'             => 'gray',
        'submitted'         => 'primary',
        'pending'           => 'gray',
        'received'          => 'info',
        'in_production'     => 'primary',
        'partially_ready'   => 'warning',
        'partially_picked'  => 'warning',
        'partially_shipped' => 'warning',
        'shipped'           => 'success',
        'completed'         => 'success',
        'cancelled'         => 'danger',
        'admin_edit'        => 'warning',
        'forecast_submitted' => 'info',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── ORDER META ── three sections side by side, one column each ──
            // Grid::make(3) alone only applies 3 columns from the `lg`
            // breakpoint up; forcing it at `default` keeps all three side by
            // side instead of stacking into full-width rows on narrower
            // admin viewports.
            Forms\Components\Grid::make(['default' => 3])
                ->schema([
                    Forms\Components\Section::make('Order')
                        ->compact()
                        // Section defaults to columnSpan('full') internally,
                        // which would make it fill the whole 3-col grid row
                        // by itself — pin it to exactly 1 column instead.
                        ->columnSpan(1)
                        ->schema([
                            Forms\Components\Select::make('club_id')
                                ->label('Club')
                                ->relationship('club', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->reactive()
                                // Club users (main or sub-user) only ever
                                // order for their own club — lock the field
                                // to it instead of offering every club.
                                ->disabled(fn () => auth()->user()?->isClub())
                                ->dehydrated()
                                ->default(fn () => auth()->user()?->isClub() ? auth()->user()->club_id : null)
                                ->afterStateUpdated(function (Set $set, $state) {
                                    $set('package_id', null);
                                    // A team belongs to exactly one club — a
                                    // stale selection from a different club
                                    // would otherwise linger in the field.
                                    $set('club_team_id', null);

                                    if ($club = Club::find($state)) {
                                        $set('shipping_address', $club->address);
                                        $set('phone', $club->phone);
                                        $set('email', $club->email);
                                    }
                                }),

                            Forms\Components\Select::make('type')
                                ->options([
                                    'package'    => 'Package',
                                    'individual' => 'Individual Items',
                                    'club_items' => 'Club Items',
                                ])
                                ->required()
                                ->reactive(),

                            // Bulk/Forecast is a separate axis from Type
                            // (package/individual/club_items still governs
                            // where item columns come from even for a Bulk
                            // order) — hidden entirely for a regular order
                            // (stays 'standard' underneath, never shown or
                            // choosable here), and only ever offered as
                            // Bulk/Forecast once an order already is one
                            // (set via the dedicated "Create Bulk Order"
                            // link, which pre-fills it to 'bulk').
                            Forms\Components\Select::make('order_kind')
                                ->label('Order Kind')
                                ->options([
                                    'bulk'     => 'Bulk Order',
                                    'forecast' => 'Forecast',
                                ])
                                ->default('standard')
                                ->reactive()
                                ->dehydrated()
                                ->visible(fn (Get $get) => in_array($get('order_kind'), ['bulk', 'forecast'], true)),

                            Forms\Components\Select::make('package_id')
                                ->label('Package')
                                ->options(fn (Get $get) => Package::where('club_id', $get('club_id'))
                                    ->where('status', 'active')
                                    ->pluck('name', 'id'))
                                ->searchable()
                                ->visible(fn (Get $get) => $get('type') === 'package')
                                ->reactive(),

                            Forms\Components\Select::make('status')
                                ->options(fn (Get $get) => $get('status') === 'draft'
                                    ? array_diff_key(self::STATUSES, array_flip(self::POST_SUBMIT_ONLY_STATUSES))
                                    : self::STATUSES)
                                ->required()
                                ->default('draft')
                                ->disabled(fn () => !auth()->user()?->isAdmin())
                                ->dehydrated()
                                // Club users can't pick a status anyway (disabled
                                // above already covers that) — this additionally
                                // keeps them from ever seeing the full internal
                                // status vocabulary (Admin Edit, Pending,
                                // Received, In Production, the Partially...
                                // states, Shipped, Completed) that a disabled
                                // <select> would otherwise still expose as
                                // options. They see their order's real current
                                // status via the plain label below instead.
                                ->hidden(fn () => auth()->user()?->isClub())
                                ->helperText(fn () => auth()->user()?->isAdmin()
                                    ? null
                                    : 'New orders save as Draft — "Submit Order" below when ready.'),

                            Forms\Components\Placeholder::make('status_display')
                                ->label('Status')
                                ->visible(fn () => auth()->user()?->isClub())
                                ->content(fn (?Order $record) => self::STATUSES[$record?->status ?? 'draft'] ?? ($record?->status ?? 'Draft')),

                            Forms\Components\Textarea::make('notes')
                                ->label('Order Notes')
                                ->rows(2),
                        ])
                        ->columns(1),

                    Forms\Components\Section::make('Order Details')
                        ->compact()
                        ->columnSpan(1)
                        ->schema([
                            Forms\Components\Select::make('club_team_id')
                                ->label('Team')
                                ->options(fn (Get $get) => ClubTeam::where('club_id', $get('club_id'))
                                    ->orderBy('sort_order')
                                    ->pluck('team_name', 'id'))
                                ->searchable()
                                ->reactive()
                                ->disabled(fn (Get $get) => blank($get('club_id')))
                                ->helperText('Pick an existing team, or type a new name to add one.')
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if (! $team = ClubTeam::find($state)) {
                                        return;
                                    }

                                    $set('team_po', $team->po_reference);
                                    $set('coach_manager', $team->coach_manager_name);

                                    if (filled($team->coach_manager_contact)) {
                                        $set('phone', $team->coach_manager_contact);
                                    }

                                    if (filled($team->coach_manager_email)) {
                                        $set('email', $team->coach_manager_email);
                                    }

                                    if (filled($team->address)) {
                                        $set('shipping_address', $team->address);
                                    }
                                })
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('team_name')->required()->maxLength(255),
                                    Forms\Components\TextInput::make('po_reference')->label('PO Reference')->maxLength(255),
                                    Forms\Components\TextInput::make('coach_manager_name')->label('Coach / Manager Name')->maxLength(255),
                                    Forms\Components\TextInput::make('coach_manager_email')->label('Coach / Manager Email')->email()->maxLength(255),
                                    Forms\Components\TextInput::make('coach_manager_contact')->label('Coach / Manager Contact')->tel()->maxLength(255),
                                    Forms\Components\Textarea::make('address')->rows(2),
                                ])
                                ->createOptionUsing(function (array $data, Get $get) {
                                    return ClubTeam::create([
                                        ...$data,
                                        'club_id'    => $get('club_id'),
                                        'status'     => 'active',
                                        'sort_order' => (ClubTeam::where('club_id', $get('club_id'))->max('sort_order') ?? 0) + 1,
                                    ])->getKey();
                                }),
                            Forms\Components\TextInput::make('team_po')
                                ->label('Team / PO #'),
                            Forms\Components\TextInput::make('coach_manager')
                                ->label('Coach / Manager'),
                            Forms\Components\TextInput::make('shipping_address')
                                ->label('Shipping Address')
                                ->helperText('Prefilled from the club once selected — editable.')
                                ->default(fn () => auth()->user()?->isClub() ? auth()->user()->club?->address : null),
                            Forms\Components\TextInput::make('phone')
                                ->label('Phone')
                                ->tel()
                                ->default(fn () => auth()->user()?->isClub() ? auth()->user()->club?->phone : null),
                            Forms\Components\TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->default(fn () => auth()->user()?->isClub() ? auth()->user()->club?->email : null),
                        ])
                        ->columns(1),

                    Forms\Components\Section::make('For Office Use')
                        ->compact()
                        ->columnSpan(1)
                        ->schema([
                            Forms\Components\DatePicker::make('order_date')
                                ->label('Order Date')
                                ->default(now()),
                            Forms\Components\TextInput::make('b2b_number')
                                ->label('B2B Number'),
                            Forms\Components\TextInput::make('qb_invoice')
                                ->label('QB Invoice #'),
                            Forms\Components\TextInput::make('brochure_link')
                                ->label('Link to Brochure')
                                ->url(),
                        ])
                        ->columns(1)
                        ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin()),
                ]),

            // ── THE SHEET ──────────────────────────────────────────────────
            Forms\Components\ViewField::make('grid_state')
                ->view('filament.forms.order-grid')
                ->viewData([
                    'products'               => self::productsCatalogForGrid(),
                    'sponsorLogos'           => self::sponsorLogosCatalogForGrid(),
                    'embellishments'         => self::embellishmentsCatalogForGrid(),
                    'embellishmentPositions' => self::embellishmentPositionsCatalogForGrid(),
                    'packages'               => self::packagesCatalogForGrid(),
                    'clubItems'              => self::clubItemsCatalogForGrid(),
                    'clubItemCrests'         => self::clubItemsCrestForGrid(),
                    'canEditPrices'          => auth()->user()?->can('manage_order_pricing') ?? false,
                    // Crest artwork (has_club_crest / crest_number) is admin-only in the
                    // grid — a club user still gets the values persisted to order_items
                    // (prefilled from the club item), they just don't edit them here.
                    'showCrestControls'      => ! (auth()->user()?->isClub() ?? false),
                ])
                ->default(json_encode(['columns' => [], 'rows' => [], 'sponsors' => [], 'embellishments' => []]))
                ->dehydrateStateUsing(fn ($state) => is_string($state) ? $state : json_encode($state))
                ->columnSpanFull(),

            Forms\Components\Hidden::make('total')->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();

                if ($user?->isClubSubUser()) {
                    // Sub-users only ever see the orders they created
                    // themselves — even within their own club.
                    $query->where('created_by', $user->id);
                } elseif ($user?->isClub()) {
                    // The main club account sees every order for its club.
                    $query->where('club_id', $user->club_id);
                }
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Order #')->sortable(),
                Tables\Columns\TextColumn::make('club.name')->label('Club')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('clubTeam.team_name')->label('Team')->sortable()->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('type')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'package'    => 'Package',
                        'individual' => 'Individual',
                        'club_items' => 'Club Items',
                        default      => $state,
                    })
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'package'    => 'primary',
                        'individual' => 'success',
                        'club_items' => 'warning',
                        default      => 'gray',
                    }),
                Tables\Columns\TextColumn::make('order_kind')
                    ->label('Order Kind')
                    ->formatStateUsing(fn (?string $state) => self::ORDER_KINDS[$state] ?? $state)
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'bulk'     => 'info',
                        'forecast' => 'warning',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('package.name')->label('Package')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->formatStateUsing(fn (?string $state) => self::STATUSES[$state] ?? $state)
                    ->badge()
                    ->color(fn (?string $state) => self::STATUS_COLORS[$state] ?? 'gray'),
                // Internal-only — separate from the customer-facing `status`
                // above, which clubs can also see. Hidden entirely for
                // anyone without manage_picking (club roles never have it).
                Tables\Columns\TextColumn::make('picking_status')
                    ->label('Picking')
                    ->formatStateUsing(fn (?string $state) => self::PICKING_STATUSES[$state] ?? $state)
                    ->badge()
                    ->color(fn (?string $state) => self::PICKING_STATUS_COLORS[$state] ?? 'gray')
                    ->description(fn (Order $record) => $record->pickingAssignee?->name)
                    ->visible(fn () => auth()->user()?->can('manage_picking') ?? false)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')->money('CAD')->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')->dateTime()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    // Club users get a trimmed list (no internal-only states);
                    // admin/staff still see every status.
                    ->options(fn () => auth()->user()?->isClub()
                        ? array_intersect_key(self::STATUSES, array_flip(self::CLUB_FILTERABLE_STATUSES))
                        : self::STATUSES),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'package'    => 'Package',
                        'individual' => 'Individual',
                        'club_items' => 'Club Items',
                    ]),
                Tables\Filters\SelectFilter::make('order_kind')
                    ->label('Order Kind')
                    ->options(self::ORDER_KINDS),
                // A club user only ever sees their own club's orders, so a
                // "filter by club" dropdown of every club is pointless here.
                Tables\Filters\SelectFilter::make('club')
                    ->relationship('club', 'name')
                    ->visible(fn () => ! (auth()->user()?->isClub() ?? false)),
            ])
            // Clicking a row opens Edit for anyone allowed to (drafts, or
            // any status for admin/sub-admin) and the read-only View page
            // otherwise (club users once an order is no longer a draft) —
            // rather than always landing on Edit and hitting a 403 there.
            ->recordUrl(fn ($record) => auth()->user()?->can('update', $record)
                ? static::getUrl('edit', ['record' => $record])
                : static::getUrl('view', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('submit')
                    ->label('Submit')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'draft' && static::canManageClubOrder($record))
                    ->action(function ($record) {
                        $record->update([
                            'status'       => 'submitted',
                            'submitted_at' => now(),
                        ]);
                    }),
                Tables\Actions\Action::make('sendForPicking')
                    ->label('Send for Picking')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('warning')
                    ->visible(fn ($record) => static::canSendForPicking($record))
                    ->form(fn ($record) => static::sendForPickingFormSchema($record))
                    ->action(fn ($record, array $data) => static::applySendForPicking($record, $data)),
                Tables\Actions\Action::make('export')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn ($record) => route('orders.export', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Creates a new draft order with the same roster, items, sponsors, and embellishments.')
                    ->visible(fn ($record) => in_array($record->status, ['draft', 'submitted'])
                        && (auth()->user()?->isAdmin() || auth()->user()?->isSubAdmin() || static::canManageClubOrder($record)))
                    ->action(function ($record, $livewire) {
                        $duplicate = static::duplicateOrder($record);

                        Notification::make()->title('Order duplicated as a new draft')->success()->send();

                        $livewire->redirect(static::getUrl('edit', ['record' => $duplicate]));
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Deep-copies an order's items (with their sponsors/embellishments) and
     * player roster (with their size/qty cells) into a brand-new draft
     * order — nothing about the source order is touched.
     */
    public static function duplicateOrder(Order $source): Order
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($source) {
            $source->loadMissing(['orderItems.sponsors', 'orderItems.embellishments', 'playerRows.itemCells']);

            $duplicate = Order::create([
                'club_id'          => $source->club_id,
                'club_team_id'     => $source->club_team_id,
                'package_id'       => $source->package_id,
                // Belongs to whoever duplicated it, not the original
                // creator — otherwise a club sub-user duplicating an order
                // wouldn't be able to see their own copy.
                'created_by'       => auth()->id(),
                'type'             => $source->type,
                'order_kind'       => $source->order_kind,
                'status'           => 'draft',
                'is_copy'          => true,
                'total'            => 0,
                'notes'            => $source->notes,
                'submitted_at'     => null,
                'team_po'          => $source->team_po,
                'coach_manager'    => $source->coach_manager,
                'shipping_address' => $source->shipping_address,
                'phone'            => $source->phone,
                'email'            => $source->email,
                'order_date'       => now(),
                'b2b_number'       => null,
                'qb_invoice'       => null,
                'brochure_link'    => $source->brochure_link,
            ]);

            $itemIdMap = [];

            foreach ($source->orderItems as $item) {
                $newItem = $duplicate->orderItems()->create([
                    'product_id'     => $item->product_id,
                    'unit_price'     => $item->unit_price,
                    'sort_order'     => $item->sort_order,
                    'has_club_crest' => $item->has_club_crest,
                    'crest_number'   => $item->crest_number,
                ]);

                $itemIdMap[$item->id] = $newItem->id;

                foreach ($item->sponsors as $sponsor) {
                    $newItem->sponsors()->create([
                        'sponsor_logo_id'           => $sponsor->sponsor_logo_id,
                        'embellishment_position_id' => $sponsor->embellishment_position_id,
                        'price'                     => $sponsor->price,
                        'brochure_link'             => $sponsor->brochure_link,
                        'override_price'            => $sponsor->override_price,
                    ]);
                }

                foreach ($item->embellishments as $embellishment) {
                    $newItem->embellishments()->create([
                        'embellishment_id'          => $embellishment->embellishment_id,
                        'embellishment_position_id' => $embellishment->embellishment_position_id,
                        'price'                     => $embellishment->price,
                        'override_price'            => $embellishment->override_price,
                    ]);
                }
            }

            foreach ($source->playerRows as $row) {
                $newRow = $duplicate->playerRows()->create([
                    'player_index' => $row->player_index,
                    'player_name'  => $row->player_name,
                    'number'       => $row->number,
                    'initials'     => $row->initials,
                    'notes'        => $row->notes,
                ]);

                foreach ($row->itemCells as $cell) {
                    $newItemId = $itemIdMap[$cell->order_item_id] ?? null;

                    if (! $newItemId) {
                        continue;
                    }

                    OrderItemCell::create([
                        'order_item_id'       => $newItemId,
                        'order_player_row_id' => $newRow->id,
                        'size'                => $cell->size,
                        'qty'                 => $cell->qty,
                        'line_total'          => $cell->line_total,
                    ]);
                }
            }

            $duplicate->recalculateTotal();

            return $duplicate;
        });
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyPermission(['manage_orders', 'create_orders']) ?? false;
    }

    /**
     * Whether the current club user (either kind) is allowed to act on this
     * specific order — the main club account for any order in its club,
     * a sub-user only for orders it created itself.
     */
    public static function canManageClubOrder($record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isClubSubUser()) {
            return $user->id === $record->created_by;
        }

        return $user->isClub() && $user->club_id === $record->club_id;
    }

    /**
     * Shared by both the "Send for Picking" table row action and its
     * mirror on the Edit Order page header — same rule everywhere: only
     * once out of draft/cancelled, and never once already fully picked.
     */
    public static function canSendForPicking(Order $record): bool
    {
        return (auth()->user()?->can('manage_picking') ?? false)
            && ! in_array($record->status, ['draft', 'cancelled'], true)
            && $record->picking_status !== 'picked';
    }

    /** @return array<\Filament\Forms\Components\Component> */
    public static function sendForPickingFormSchema(Order $record): array
    {
        return [
            Forms\Components\Select::make('employee_id')
                ->label('Assign to')
                ->options(fn () => \App\Models\User::permission('pick_orders')->orderBy('name')->pluck('name', 'id'))
                ->default($record->picking_assigned_to)
                ->searchable()
                ->required(),
        ];
    }

    public static function applySendForPicking(Order $record, array $data): void
    {
        $previousAssignee = $record->pickingAssignee?->name;

        $record->update([
            'picking_assigned_to' => $data['employee_id'],
            'picking_sent_at'     => now(),
        ]);

        $employeeName = \App\Models\User::find($data['employee_id'])?->name ?? 'employee';

        // Order doesn't use the LogsActivity trait (see OrderActivityLog
        // widget) — picking's stock writes log against the ProductWarehouseStock
        // subject, not the order, so without an explicit order-scoped entry
        // here (and in OrderPicking's save/finish actions) none of it would
        // ever show up on the order's own "Order Log" widget.
        activity('order')
            ->causedBy(auth()->user())
            ->performedOn($record)
            ->withChanges([
                'attributes' => ['picking_assigned_to' => $employeeName],
                'old'        => ['picking_assigned_to' => $previousAssignee],
            ])
            ->log("Sent for picking to {$employeeName}");

        \Filament\Notifications\Notification::make()->title('Order sent for picking')->success()->send();
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * A second, direct link straight to the Bulk Order / Forecast create
     * form — otherwise it's just another Type option someone has to
     * remember to pick after opening the regular Create Order form.
     */
    public static function getNavigationItems(): array
    {
        return [
            ...parent::getNavigationItems(),
            NavigationItem::make('Create Bulk Order')
                ->group(static::getNavigationGroup())
                ->icon('heroicon-o-calendar-days')
                ->sort(static::getNavigationSort() + 1)
                ->visible(fn () => static::canCreate())
                ->isActiveWhen(fn () => request()->routeIs(static::getRouteBaseName().'.create-bulk'))
                ->url(fn () => static::getUrl('create-bulk')),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'       => Pages\ListOrders::route('/'),
            'create'      => Pages\CreateOrder::route('/create'),
            'create-bulk' => Pages\CreateBulkOrder::route('/create-bulk'),
            'view'        => Pages\ViewOrder::route('/{record}'),
            'edit'        => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    // ── Grid catalog data ───────────────────────────────────────────────

    private static function productsCatalogForGrid(): array
    {
        // The live catalog can run into the thousands of rows with tens of
        // thousands of attribute pivot rows. Hydrating those as Eloquent
        // models (each attribute row becomes a full Attribute model wrapped
        // in a Pivot instance) is what actually blows the memory limit —
        // pull everything through the query builder instead, which returns
        // lightweight stdClass rows.
        $products = \Illuminate\Support\Facades\DB::table('products')
            ->select(['id', 'name', 'retail_price'])
            ->whereNull('parent_sku')
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        $sizesByProductId = \Illuminate\Support\Facades\DB::table('attribute_product')
            ->join('attributes', 'attributes.id', '=', 'attribute_product.attribute_id')
            ->whereIn('attribute_product.product_id', $products->pluck('id'))
            ->select(['attribute_product.product_id', 'attributes.name'])
            ->orderBy('attributes.position')
            ->orderBy('attributes.name')
            ->get()
            ->groupBy('product_id');

        return $products
            ->map(fn ($product) => [
                'id'    => $product->id,
                'name'  => $product->name,
                'price' => (float) $product->retail_price,
                'sizes' => ($sizesByProductId->get($product->id) ?? collect())->pluck('name')->values()->all(),
            ])
            ->values()
            ->all();
    }

    private static function sponsorLogosCatalogForGrid(): array
    {
        return SponsorLogo::with('position')->orderBy('name')->get()
            ->map(fn (SponsorLogo $logo) => [
                'id'          => $logo->id,
                'name'        => $logo->name,
                'price'       => (float) $logo->price,
                'club_id'     => $logo->club_id,
                'position_id' => $logo->embellishment_position_id,
            ])
            ->values()
            ->all();
    }

    private static function embellishmentPositionsCatalogForGrid(): array
    {
        return EmbellishmentPosition::orderBy('name')->get()
            ->map(fn (EmbellishmentPosition $p) => [
                'id'   => $p->id,
                'name' => $p->name,
            ])
            ->values()
            ->all();
    }

    private static function embellishmentsCatalogForGrid(): array
    {
        return Embellishment::orderBy('name')->get()
            ->map(fn (Embellishment $e) => [
                'id'          => $e->id,
                'name'        => $e->name,
                'cost'        => (float) $e->cost,
                'position_id' => $e->embellishment_position_id,
            ])
            ->values()
            ->all();
    }

    /**
     * club id => {product_id: online store price} — both which products
     * are assigned to a club (membership = the map having that key) and
     * what to auto-fill a Club Items order's unit price with, sourced from
     * ClubResource's "Assigned Items > Online Store Price" field rather
     * than the plain catalog retail_price.
     *
     * @return array<int, array<int, float>>
     */
    private static function clubItemsCatalogForGrid(): array
    {
        $pairs = \Illuminate\Support\Facades\DB::table('club_product')->select(['club_id', 'product_id', 'online_store_price'])->get();

        return $pairs->groupBy('club_id')
            ->map(fn ($rows) => $rows->pluck('online_store_price', 'product_id')
                ->map(fn ($price) => (float) $price)
                ->all())
            ->all();
    }

    /**
     * club id => {product_id: {has_club_crest, crest_number}} — sourced
     * from ClubResource's "Assigned Items > Club Crest / Crest #" fields so
     * a Club Items order's new columns prefill the club's own crest setup.
     *
     * @return array<int, array<int, array{has_club_crest: bool, crest_number: int}>>
     */
    private static function clubItemsCrestForGrid(): array
    {
        $pairs = \Illuminate\Support\Facades\DB::table('club_product')
            ->select(['club_id', 'product_id', 'has_club_crest', 'crest_number'])
            ->get();

        return $pairs->groupBy('club_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->product_id => [
                'has_club_crest' => (bool) $r->has_club_crest,
                'crest_number'   => (int) ($r->crest_number ?? 1),
            ]])->all())
            ->all();
    }

    private static function packagesCatalogForGrid(): array
    {
        // Queried directly off package_product (rather than through
        // Package::products()) so the sponsor/embellishment predefined on
        // each package item can be eager-loaded in one shot.
        $packageProducts = PackageProduct::with(['sponsors', 'embellishments'])->orderBy('sort_order')->get();

        $productPrices = Product::whereIn('id', $packageProducts->pluck('product_id')->unique())
            ->pluck('retail_price', 'id');

        $packageProductsByPackage = $packageProducts->groupBy('package_id');

        return Package::all()->map(function (Package $package) use ($packageProductsByPackage, $productPrices) {
            $items = ($packageProductsByPackage->get($package->id) ?? collect())
                ->map(fn (PackageProduct $packageProduct) => [
                    'product_id'     => $packageProduct->product_id,
                    'price'          => (float) ($packageProduct->per_item_price ?? $productPrices->get($packageProduct->product_id) ?? 0),
                    'has_club_crest' => (bool) $packageProduct->has_club_crest,
                    'crest_number'   => (int) ($packageProduct->crest_number ?? 1),
                    'sponsors'       => $packageProduct->sponsors->map(fn ($s) => [
                        'sponsor_logo_id'           => $s->sponsor_logo_id,
                        'embellishment_position_id' => $s->embellishment_position_id,
                        'brochure_link'             => $s->brochure_link,
                        'override_price'            => is_null($s->override_price) ? null : (float) $s->override_price,
                    ])->values()->all(),
                    'embellishments' => $packageProduct->embellishments->map(fn ($e) => [
                        'embellishment_id'          => $e->embellishment_id,
                        'embellishment_position_id' => $e->embellishment_position_id,
                        'override_price'            => is_null($e->override_price) ? null : (float) $e->override_price,
                    ])->values()->all(),
                ])
                ->values()
                ->all();

            return [
                'id'      => $package->id,
                'club_id' => $package->club_id,
                'price'   => (float) $package->price,
                'items'   => $items,
            ];
        })->values()->all();
    }
}
