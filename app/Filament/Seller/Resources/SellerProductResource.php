<?php

namespace App\Filament\Seller\Resources;

use App\Filament\Seller\Resources\SellerProductResource\Pages;
use App\Filament\Seller\Resources\SellerProductResource\RelationManagers;
use App\Models\SellerProduct;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SellerProductResource extends Resource
{
    protected static ?string $model = SellerProduct::class;

    protected static ?string $navigationLabel = 'Products';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Product Details')
                    ->description('Enter the basic information about the product.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->helperText('The display name for this product'),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->native(false)
                            ->required()
                            ->helperText('The category this product belongs to'),
                            // ->suffixAction(
                            //     Forms\Components\Actions\Action::make('viewCategory')
                            //         ->icon('heroicon-m-arrow-top-right-on-square')
                            //         ->url(fn($record) => CategoryResource::getUrl('edit', ['record' => $record->category_id]))
                            // ),
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->helperText('Stock Keeping Unit - A unique identifier for this product'),

                        Forms\Components\Textarea::make('description')
                            ->columnSpanFull()
                            ->helperText('A detailed description of the product'),
                        Forms\Components\KeyValue::make('attributes')
                            ->columnSpanFull()
                            ->keyLabel('Attribute')
                            ->valueLabel('Value')
                            ->helperText('Custom attributes for this product (e.g. Brand: Nike, Material: Cotton)')
                            ->dehydrateStateUsing(fn($state) => is_array($state) ? $state : [])
                            ->reorderable()
                            ->editableKeys()
                            ->editableValues(),
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
                Tables\Columns\TextColumn::make('category.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('seller_variants_count')
                    ->label('Variants')
                    ->counts('sellerVariants')
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
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('has_variants')
                    ->query(fn (Builder $query): Builder => $query->has('sellerVariants'))
                    ->label('Has variants'),
                Tables\Filters\Filter::make('no_variants') 
                    ->query(fn (Builder $query): Builder => $query->doesntHave('sellerVariants'))
                    ->label('No variants'),
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
            RelationManagers\VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellerProducts::route('/'),
            'create' => Pages\CreateSellerProduct::route('/create'),
            'edit' => Pages\EditSellerProduct::route('/{record}/edit'),
        ];
    }
}
