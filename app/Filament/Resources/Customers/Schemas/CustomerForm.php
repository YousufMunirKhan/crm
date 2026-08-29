<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->required()
                    ->default('prospect'),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('business_name'),
                TextInput::make('owner_name'),
                TextInput::make('contact_person_2_name'),
                TextInput::make('contact_person_2_phone')
                    ->tel(),
                TextInput::make('phone')
                    ->tel()
                    ->required(),
                TextInput::make('whatsapp_number'),
                TextInput::make('sms_number'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('email_secondary')
                    ->email(),
                TextInput::make('address'),
                TextInput::make('postcode'),
                TextInput::make('city'),
                TextInput::make('vat_number'),
                Textarea::make('notes')
                    ->columnSpanFull(),
                TextInput::make('source'),
                TextInput::make('anydesk_rustdesk'),
                TextInput::make('passwords')
                    ->password(),
                TextInput::make('epos_type'),
                TextInput::make('lic_days')
                    ->numeric(),
                DatePicker::make('birthday'),
                TextInput::make('category'),
                TextInput::make('portal_password')
                    ->password(),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
            ]);
    }
}
