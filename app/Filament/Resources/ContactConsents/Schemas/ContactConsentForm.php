<?php

namespace App\Filament\Resources\ContactConsents\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ContactConsentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('identifier')
                    ->required(),
                TextInput::make('channel')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('unknown'),
                DateTimePicker::make('opt_in_at'),
                DateTimePicker::make('opt_out_at'),
                TextInput::make('source'),
                TextInput::make('lawful_basis'),
                Textarea::make('evidence')
                    ->columnSpanFull(),
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
            ]);
    }
}
