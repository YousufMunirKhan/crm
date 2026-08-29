<?php

namespace App\Filament\Resources\Tickets\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('ticket_number')
                    ->required(),
                TextInput::make('source')
                    ->required()
                    ->default('crm'),
                TextInput::make('pos_external_id'),
                TextInput::make('pos_shop_name'),
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('assigned_to')
                    ->numeric(),
                TextInput::make('subject')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('reference_url')
                    ->url(),
                Select::make('priority')
                    ->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'])
                    ->default('medium')
                    ->required(),
                TextInput::make('estimated_resolve_hours')
                    ->numeric(),
                Select::make('status')
                    ->options([
            'open' => 'Open',
            'in_progress' => 'In progress',
            'on_hold' => 'On hold',
            'resolved' => 'Resolved',
            'closed' => 'Closed',
        ])
                    ->default('open')
                    ->required(),
                DateTimePicker::make('sla_due_at'),
                DateTimePicker::make('resolved_at'),
                TextInput::make('pos_telephone')
                    ->tel(),
                TextInput::make('pos_address'),
                TextInput::make('pos_computer_name'),
                TextInput::make('pos_support_status')
                    ->required()
                    ->default('pending'),
                Textarea::make('pos_resolution_notes')
                    ->columnSpanFull(),
                DateTimePicker::make('pos_submitted_at'),
                DateTimePicker::make('pos_sent_at'),
            ]);
    }
}
