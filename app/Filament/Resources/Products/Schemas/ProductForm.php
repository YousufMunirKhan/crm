<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->helperText('Optional. Must be unique across the catalogue.'),
                        TextInput::make('category')
                            ->maxLength(255)
                            ->datalist(fn () => \App\Modules\CRM\Models\Product::query()
                                ->whereNotNull('category')
                                ->distinct()
                                ->orderBy('category')
                                ->pluck('category')
                                ->all())
                            ->helperText('Drives target attainment - keep these consistent.'),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Pricing')
                    ->columns(3)
                    ->schema([
                        TextInput::make('unit_price')
                            ->label('Sale price')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('£'),
                        TextInput::make('cost_price')
                            ->label('Cost price')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('£')
                            // Cost drives margin and is hidden from the API;
                            // keep it out of non-management hands here too.
                            ->visible(fn () => auth()->user()?->isRole('Admin')
                                || auth()->user()?->isRole('System Admin')
                                || auth()->user()?->isRole('Manager'))
                            ->helperText('Used for margin reporting.'),
                        TextInput::make('tax_rate')
                            ->label('Tax rate')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Blank uses the company VAT rate.'),
                        TextInput::make('currency')
                            ->default('GBP')
                            ->required()
                            ->maxLength(3),
                        TextInput::make('unit')
                            ->maxLength(32)
                            ->placeholder('each, month, hour'),
                    ]),

                Section::make('Presentation')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('image_path')
                            ->image()
                            ->directory('products')
                            ->imageEditor(),
                        Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Inactive products stay on historic quotes and invoices but cannot be added to new ones.'),
                    ]),
            ]);
    }
}
