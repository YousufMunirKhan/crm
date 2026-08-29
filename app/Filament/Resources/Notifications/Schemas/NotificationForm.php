<?php

namespace App\Filament\Resources\Notifications\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class NotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->required(),
                TextInput::make('notifiable_type')
                    ->required()
                    ->default('AppModelsUser'),
                TextInput::make('notifiable_id')
                    ->required()
                    ->numeric(),
                TextInput::make('title'),
                Textarea::make('message')
                    ->columnSpanFull(),
                TextInput::make('data'),
                DateTimePicker::make('read_at'),
            ]);
    }
}
