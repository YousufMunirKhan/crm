<?php

namespace App\Filament\Resources\MessageTemplates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MessageTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('category')
                    ->required()
                    ->default('general'),
                Textarea::make('message')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('variables')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
