<?php

namespace App\Filament\Resources\ImportLogs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ImportLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->numeric(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('total_rows')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('imported')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('skipped')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('errors')
                    ->columnSpanFull(),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
            ]);
    }
}
