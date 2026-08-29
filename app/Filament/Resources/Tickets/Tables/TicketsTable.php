<?php

namespace App\Filament\Resources\Tickets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticket_number')
                    ->searchable(),
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('pos_external_id')
                    ->searchable(),
                TextColumn::make('pos_shop_name')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('assigned_to')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable(),
                TextColumn::make('reference_url')
                    ->searchable(),
                TextColumn::make('priority')
                    ->badge(),
                TextColumn::make('estimated_resolve_hours')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('sla_due_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pos_telephone')
                    ->searchable(),
                TextColumn::make('pos_address')
                    ->searchable(),
                TextColumn::make('pos_computer_name')
                    ->searchable(),
                TextColumn::make('pos_support_status')
                    ->searchable(),
                TextColumn::make('pos_submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('pos_sent_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
