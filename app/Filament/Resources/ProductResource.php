<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Catalogue';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product Info')->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
                Forms\Components\Textarea::make('description')->rows(3)->columnSpanFull(),
                Forms\Components\TextInput::make('barcode')
                    ->label('Barcode')
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'Another product already uses this barcode.']),
                Forms\Components\TextInput::make('parent_sku')
                    ->helperText('Leave blank if this is a parent/master product.'),
                Forms\Components\TextInput::make('default_sku')
                    ->label('Default SKU')
                    ->unique(ignoreRecord: true)
                    ->validationMessages(['unique' => 'Another product already uses this Default SKU.']),
                Forms\Components\TextInput::make('size'),
                Forms\Components\Select::make('status')
                    ->options(['Active' => 'Active', 'Inactive' => 'Inactive'])
                    ->default('Active')
                    ->required(),
                Forms\Components\TextInput::make('macro_category')
                    ->label('Macro Category')
                    ->datalist(fn () => \App\Models\Product::whereNotNull('macro_category')
                        ->distinct()
                        ->orderBy('macro_category')
                        ->pluck('macro_category')
                        ->all()),
                Forms\Components\Select::make('co_sponsorship_id')
                    ->label('Co-Sponsorship')
                    ->relationship('coSponsorship', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Only assign on the parent product.'),
            ])->columns(2),

            Forms\Components\Section::make('Stock & Pricing')->schema([
                Forms\Components\TextInput::make('qty')
                    ->label('Stock Qty')->required()->numeric()->default(0)->minValue(0),
                Forms\Components\TextInput::make('retail_price')
                    ->label('Retail Price')->required()->numeric()->default(0)->prefix('$'),
                Forms\Components\Toggle::make('on_backorder')->label('On Backorder')->reactive(),
                Forms\Components\DatePicker::make('backorder_date')
                    ->label('Expected Back In Stock')
                    ->visible(fn ($get) => $get('on_backorder')),
            ])->columns(2),

            Forms\Components\Section::make('Media')->schema([
                Forms\Components\TextInput::make('image_link')->label('Image URL')->url()->columnSpanFull(),
                Forms\Components\Textarea::make('gallery_links')
                    ->label('Gallery Image URLs')
                    ->helperText('Comma-separated list of URLs.')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),

            Forms\Components\Section::make('Classification')->schema([
                Forms\Components\TextInput::make('year'),
                Forms\Components\TextInput::make('available_until_year')->label('Available Until Year'),
                Forms\Components\TextInput::make('total_look')->label('Total Look'),
                Forms\Components\TextInput::make('weight')->numeric()->step(0.01),
            ])->columns(4),

            Forms\Components\Section::make('Colors')->schema([
                Forms\Components\TextInput::make('color1_code')->label('Color 1 Code'),
                Forms\Components\TextInput::make('color1_label')->label('Color 1 Label'),
                Forms\Components\TextInput::make('color2_code')->label('Color 2 Code'),
                Forms\Components\TextInput::make('color2_label')->label('Color 2 Label'),
            ])->columns(4),

            Forms\Components\Section::make('Attributes')->schema([
                Forms\Components\Select::make('attributes')
                    ->label('Sizes / Attributes')
                    ->relationship('attributes', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // No "all" option: the catalog is ~30k products and the
            // Attributes column eager-loads a many-to-many relation, so
            // "all" hydrates 60k+ pivot rows and blows the request memory
            // limit. Filament also auto-clears a now-invalid "all" value
            // that was previously saved to a user's session.
            ->paginated([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->wrap(),
                Tables\Columns\TextColumn::make('default_sku')->label('Default SKU')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('size')->searchable(),
                Tables\Columns\TextColumn::make('parent_sku')->label('Parent SKU')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('barcode')->label('Barcode')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\ViewColumn::make('status')
                    ->view('filament.tables.columns.products-editable-cell')
                    ->viewData(['type' => 'select', 'options' => ['Active' => 'Active', 'Inactive' => 'Inactive']])
                    ->sortable(),
                Tables\Columns\ViewColumn::make('macro_category')->label('Macro Category')
                    ->view('filament.tables.columns.products-editable-cell')
                    ->viewData(['type' => 'text'])
                    ->searchable()->toggleable(),
                Tables\Columns\ViewColumn::make('qty')->label('Stock')
                    ->view('filament.tables.columns.products-editable-cell')
                    ->viewData(['type' => 'number'])
                    ->sortable(),
                Tables\Columns\ViewColumn::make('on_backorder')->label('Backorder')
                    ->view('filament.tables.columns.products-editable-cell')
                    ->viewData(['type' => 'checkbox', 'format' => fn ($value) => $value ? 'Yes' : 'No']),
                Tables\Columns\ViewColumn::make('retail_price')->label('Price')
                    ->view('filament.tables.columns.products-editable-cell')
                    ->viewData(['type' => 'number', 'step' => '0.01', 'format' => fn ($value) => '$'.number_format((float) $value, 2)])
                    ->sortable(),
                Tables\Columns\TextColumn::make('coSponsorship.name')->label('Co-Sponsorship')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('attributes.name')->label('Attributes')->badge()->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('parent_only')
                    ->label('Parent Products Only')
                    ->query(fn (Builder $query) => $query->whereNull('parent_sku'))
                    ->toggle(),
                Tables\Filters\TernaryFilter::make('on_backorder')->label('On Backorder'),
                Tables\Filters\SelectFilter::make('status')->options(['Active' => 'Active', 'Inactive' => 'Inactive']),
                Tables\Filters\SelectFilter::make('macro_category')
                    ->label('Macro Category')
                    ->options(fn () => Product::whereNotNull('macro_category')
                        ->distinct()
                        ->orderBy('macro_category')
                        ->pluck('macro_category', 'macro_category')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\Action::make('inlineEdit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->action(fn ($record, $livewire) => $livewire->dispatch('start-inline-edit', ids: [$record->getKey()])),
                Tables\Actions\EditAction::make()->label('Full Edit'),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\BulkAction::make('inlineEdit')
                    ->label('Inline Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->action(fn ($records, $livewire) => $livewire->dispatch('start-inline-edit', ids: $records->pluck('id')->all()))
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\BulkAction::make('bulkUpdate')
                    ->label('Bulk Update')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options(['Active' => 'Active', 'Inactive' => 'Inactive'])
                            ->placeholder('Leave unchanged'),
                        Forms\Components\TextInput::make('macro_category')
                            ->label('Macro Category')
                            ->placeholder('Leave unchanged'),
                        Forms\Components\Select::make('on_backorder')
                            ->label('On Backorder')
                            ->options(['1' => 'Yes', '0' => 'No'])
                            ->placeholder('Leave unchanged'),
                        Forms\Components\TextInput::make('retail_price')
                            ->label('Retail Price')
                            ->numeric()
                            ->prefix('$')
                            ->placeholder('Leave unchanged'),
                        Forms\Components\TextInput::make('qty')
                            ->label('Stock Qty')
                            ->numeric()
                            ->placeholder('Leave unchanged'),
                    ])
                    ->action(function (\Illuminate\Support\Collection $records, array $data) {
                        $updates = [];

                        foreach (['status', 'macro_category', 'on_backorder', 'retail_price', 'qty'] as $field) {
                            $value = $data[$field] ?? null;

                            if ($value === null || $value === '') {
                                continue;
                            }

                            $updates[$field] = $field === 'on_backorder' ? (bool) $value : $value;
                        }

                        if (empty($updates)) {
                            return;
                        }

                        $records->each(fn (Product $record) => $record->update($updates));
                    })
                    ->deselectRecordsAfterCompletion()
                    ->successNotificationTitle('Products updated'),
                Tables\Actions\BulkAction::make('export')
                    ->label('Export Selected to Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (\Illuminate\Support\Collection $records) {
                        // Selected sets are normally small, but keep the same
                        // ceiling as the full-catalog export in ProductController
                        // in case someone select-alls the whole list.
                        ini_set('memory_limit', '1024M');

                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\ProductsExport($records->pluck('id')->all()),
                            'products-selected-' . now()->format('Y-m-d') . '.xlsx',
                        );
                    })
                    ->deselectRecordsAfterCompletion(),
                Tables\Actions\DeleteBulkAction::make(),
            ])])
            // Otherwise Filament defaults a whole-row click to the first
            // available view/edit action's URL — here that's "Full Edit",
            // which fights with clicking inside a row to start inline edits.
            ->recordUrl(null);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_products') ?? false;
    }
}
