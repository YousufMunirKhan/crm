<?php

namespace App\Filament\Resources\ContactConsents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactConsentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('identifier')
                    ->searchable(),
                TextColumn::make('channel')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('opt_in_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('opt_out_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('lawful_basis')
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
