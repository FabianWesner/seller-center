<?php

namespace App\Filament\Seller\Resources;

use App\Filament\Seller\Resources\SellerVariantResource\Pages;
use App\Filament\Seller\Resources\SellerVariantResource\RelationManagers;
use App\Models\SellerVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class SellerVariantResource extends Resource
{

    // use Forms\Concerns\InteractsWithForms;

    protected static ?string $model = SellerVariant::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Variants';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Variant Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->helperText('The display name for this variant'),
                        Forms\Components\Select::make('seller_product_id')
                            ->relationship('sellerProduct', 'name')
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->helperText('The product this variant belongs to.')
                            ->suffixAction(function (Forms\Components\Actions\Action $action, $record) {
                                if (!$record) return null;
                                return $action
                                    ->make('viewProduct')
                                    ->icon('heroicon-m-arrow-top-right-on-square')
                                    ->url(SellerProductResource::getUrl('edit', ['record' => $record->seller_product_id]));
                            }),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->helperText('Stock Keeping Unit - A unique identifier for this variant')
                            ->hint(function ($record) {
                                if (!$record || !$record->sellerProduct || !$record->sellerProduct->sku) {
                                    return null;
                                }
                                return "Product SKU: {$record->sellerProduct->sku}";
                            }),
                        Forms\Components\Select::make('status_id')
                            ->relationship('status', 'name')
                            ->native(false)
                            ->required()
                            ->helperText('Current status of this variant (draft, pending approval, etc.)'),
                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull()
                            ->helperText('Detailed description of this specific variant which overrides the product description'),
                        Forms\Components\KeyValue::make('attributes')
                            ->columnSpanFull()
                            ->helperText('Custom attributes for this variant (e.g. Color: Red, Size: Large)')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->dehydrateStateUsing(fn($state) => is_array($state) ? $state : [])
                            ->reorderable()
                            ->editableKeys()
                            ->editableValues(),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('fillDummyData')
                                ->label('Fill with dummy data')
                                ->icon('heroicon-m-sparkles')
                                ->action(function ($livewire) {
                                    $livewire->form->fill([
                                        'name' => fake()->words(3, true),
                                        'sku' => strtoupper(fake()->bothify('??-####')),
                                        'description' => fake()->paragraph(),
                                        'seller_product_id' => \App\Models\SellerProduct::inRandomOrder()->first()->id,
                                        'status_id' => \App\Models\Status::inRandomOrder()->first()->id,
                                        'attributes' => [
                                            'Color' => fake()->colorName(),
                                            'Size' => fake()->randomElement(['Small', 'Medium', 'Large']),
                                            'Material' => fake()->randomElement(['Cotton', 'Polyester', 'Wool', 'Silk']),
                                            'Weight' => fake()->numberBetween(100, 1000) . 'g'
                                        ]
                                    ]);
                                })
                        ]),
                    ])
                    ->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'pending_approval' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'active' => 'success',
                        'inactive' => 'gray',
                        'archived' => 'danger'
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('prices')
                    ->label(fn() => 'Price (' . \App\Models\Currency::where('is_default', true)->first()->code . ')')
                    ->money(fn() => \App\Models\Currency::where('is_default', true)->first()->code)
                    ->getStateUsing(function ($record) {
                        $defaultCurrency = \App\Models\Currency::where('is_default', true)->first();
                        $price = $record->prices()->whereHas('currency', function ($query) use ($defaultCurrency) {
                            $query->where('id', $defaultCurrency->id);
                        })->first();

                        return $price ? (float) $price->amount / 100 : null;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->relationship('status', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('seller_product')
                    ->relationship('sellerProduct', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Product'),
                Tables\Filters\Filter::make('price_range')
                    ->form([
                        Forms\Components\TextInput::make('price_from')
                            ->numeric()
                            ->label('Price from')
                            ->prefix(\App\Models\Currency::where('is_default', true)->first()->symbol),
                        Forms\Components\TextInput::make('price_to')
                            ->numeric()
                            ->label('Price to')
                            ->prefix(\App\Models\Currency::where('is_default', true)->first()->symbol),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $defaultCurrency = \App\Models\Currency::where('is_default', true)->first();
                        return $query
                            ->when(
                                $data['price_from'],
                                fn(Builder $query, $price): Builder => $query->whereHas(
                                    'prices',
                                    fn($q) => $q->where('currency_id', $defaultCurrency->id)
                                        ->where('amount', '>=', $price * 100)
                                )
                            )
                            ->when(
                                $data['price_to'],
                                fn(Builder $query, $price): Builder => $query->whereHas(
                                    'prices',
                                    fn($q) => $q->where('currency_id', $defaultCurrency->id)
                                        ->where('amount', '<=', $price * 100)
                                )
                            );
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PricesRelationManager::class,
            RelationManagers\StocksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellerVariants::route('/'),
            'create' => Pages\CreateSellerVariant::route('/create'),
            'edit' => Pages\EditSellerVariant::route('/{record}/edit'),
        ];
    }
}
