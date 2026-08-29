<?php

namespace App\Filament\Resources\Campaigns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('channel')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('draft'),
                TextInput::make('template_id')
                    ->numeric(),
                TextInput::make('template_type'),
                TextInput::make('audience_filters'),
                TextInput::make('audience_segment_id')
                    ->numeric(),
                DateTimePicker::make('scheduled_at'),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('completed_at'),
                TextInput::make('recipient_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('sent_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('failed_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('skipped_count')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
