<?php

namespace App\Filament\Resources\EmployeeTargets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EmployeeTargetForm
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
                TextInput::make('target_appointments')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('target_sales')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('target_revenue')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('meta')
                    ->columnSpanFull(),
            ]);
    }
}
