<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('role_id')
                    ->relationship('role', 'name'),
                Select::make('employee_type')
                    ->options([
            'field_worker' => 'Field worker',
            'call_center' => 'Call center',
            'ticket_manager' => 'Ticket manager',
        ]),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('commission_eligible')
                    ->required(),
                Textarea::make('nav_permissions')
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('phone')
                    ->tel(),
                Textarea::make('address')
                    ->columnSpanFull(),
                DatePicker::make('hire_date'),
                DatePicker::make('date_of_birth'),
                TextInput::make('bank_account_name'),
                TextInput::make('bank_name'),
                TextInput::make('bank_sort_code'),
                TextInput::make('bank_account_number'),
                DateTimePicker::make('contract_sent_at'),
                TextInput::make('contract_pdf_path'),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required(),
            ]);
    }
}
