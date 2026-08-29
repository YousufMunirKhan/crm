<?php

namespace App\Filament\Resources\Salaries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SalaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                TextInput::make('month')
                    ->required(),
                TextInput::make('base_salary')
                    ->required()
                    ->numeric(),
                TextInput::make('allowances')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('house_allowance')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('medical_allowance')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('other_allowance')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('bonuses')
                    ->columnSpanFull(),
                TextInput::make('deductions')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('tax')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('loan_deduction')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('other_deduction')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('deductions_detail')
                    ->columnSpanFull(),
                TextInput::make('net_salary')
                    ->required()
                    ->numeric(),
                TextInput::make('currency')
                    ->required()
                    ->default('GBP'),
                TextInput::make('attendance_days')
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
