<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('GBP'),
                TextInput::make('reason')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('category'),
                TextInput::make('status')
                    ->required()
                    ->default('open'),
                TextInput::make('created_by')
                    ->required()
                    ->numeric(),
            ]);
    }
}
