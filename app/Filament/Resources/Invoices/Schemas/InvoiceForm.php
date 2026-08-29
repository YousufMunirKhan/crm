<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('invoice_number')
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required(),
                Select::make('lead_id')
                    ->relationship('lead', 'id'),
                TextInput::make('created_by')
                    ->numeric(),
                DatePicker::make('invoice_date')
                    ->required(),
                DatePicker::make('due_date'),
                TextInput::make('subtotal')
                    ->required()
                    ->numeric(),
                TextInput::make('vat_rate')
                    ->required()
                    ->numeric()
                    ->default(20.0),
                TextInput::make('vat_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('total')
                    ->required()
                    ->numeric(),
                TextInput::make('amount_paid')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Select::make('currency')
                    ->options(['GBP' => 'G b p'])
                    ->default('GBP')
                    ->required(),
                Select::make('status')
                    ->options([
            'draft' => 'Draft',
            'sent' => 'Sent',
            'partially_paid' => 'Partially paid',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
        ])
                    ->default('draft')
                    ->required(),
            ]);
    }
}
