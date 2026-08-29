<?php

namespace App\Filament\Resources\AudienceSegments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AudienceSegmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('filters')
                    ->required(),
                Toggle::make('is_shared')
                    ->required(),
                TextInput::make('last_count')
                    ->numeric(),
                DateTimePicker::make('last_counted_at'),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
