<?php

namespace App\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
                Select::make('product_id')
                    ->relationship('product', 'name'),
                Select::make('converted_from_activity_id')
                    ->relationship('convertedFromActivity', 'id'),
                Select::make('stage')
                    ->options([
            'follow_up' => 'Follow up',
            'lead' => 'Lead',
            'hot_lead' => 'Hot lead',
            'quotation' => 'Quotation',
            'won' => 'Won',
            'lost' => 'Lost',
        ])
                    ->required(),
                TextInput::make('source'),
                TextInput::make('assigned_to')
                    ->numeric(),
                TextInput::make('pipeline_value')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('lost_reason'),
                DateTimePicker::make('next_follow_up_at'),
                DatePicker::make('expected_closing_date'),
            ]);
    }
}
