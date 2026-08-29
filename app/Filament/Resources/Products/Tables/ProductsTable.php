<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('category')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('unit_price')
                    ->label('Price')
                    ->money('GBP')
                    ->sortable()
                    ->placeholder('Not priced'),
                TextColumn::make('margin')
                    ->label('Margin')
                    ->state(fn ($record) => $record->marginAttribute())
                    ->money('GBP')
                    ->placeholder('—')
                    // Cost price is management-only, so the derived margin is too.
                    ->visible(fn () => auth()->user()?->isRole('Admin')
                        || auth()->user()?->isRole('System Admin')
                        || auth()->user()?->isRole('Manager')),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('category')
                    ->options(fn () => \App\Modules\CRM\Models\Product::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label('Active'),
                TernaryFilter::make('unit_price')
                    ->label('Has a price')
                    ->nullable()
                    ->attribute('unit_price'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No products yet')
            ->emptyStateDescription('Products added here are used on quotes and invoices.');
    }
}
