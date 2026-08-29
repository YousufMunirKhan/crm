<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Cross-sell links for a product.
 *
 * product_relationships, its model relations, the read API and the UI panel
 * all existed already - but nothing anywhere could write a row, so the
 * suggestions panel rendered empty in production. This is the write path,
 * including the relationship_type pivot that was previously hardcoded to
 * 'suggest' and so lost the upsell / cross-sell distinction.
 */
class SuggestedProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'suggestedProducts';

    protected static ?string $title = 'Cross-sell & upsell';

    protected static ?string $recordTitleAttribute = 'name';

    /** @return array<string, string> */
    public static function relationshipTypes(): array
    {
        return [
            'suggest' => 'Suggest',
            'upsell' => 'Upsell',
            'cross_sell' => 'Cross-sell',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('relationship_type')
                ->options(self::relationshipTypes())
                ->default('suggest')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('relationship_type')
                    ->label('Type')
                    ->badge()
                    // Lives on the pivot, not the product.
                    ->state(fn ($record) => self::relationshipTypes()[$record->pivot->relationship_type] ?? $record->pivot->relationship_type)
                    ->color(fn ($record) => match ($record->pivot->relationship_type) {
                        'upsell' => 'success',
                        'cross_sell' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('unit_price')
                    ->label('Price')
                    ->money('GBP')
                    ->placeholder('Not priced'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'sku'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->label('Product to link'),
                        Select::make('relationship_type')
                            ->options(self::relationshipTypes())
                            ->default('suggest')
                            ->required(),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No linked products')
            ->emptyStateDescription('Linked products appear as suggestions on leads and the customer workspace.');
    }
}
