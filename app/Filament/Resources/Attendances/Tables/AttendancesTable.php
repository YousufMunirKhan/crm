<?php

namespace App\Filament\Resources\Attendances\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('check_in_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('check_in_photo_path')
                    ->searchable(),
                TextColumn::make('check_in_latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('check_in_longitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('check_in_location_name')
                    ->searchable(),
                TextColumn::make('check_in_location_accuracy')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('check_in_location_captured_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('check_out_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('check_out_photo_path')
                    ->searchable(),
                TextColumn::make('check_out_latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('check_out_longitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('check_out_location_name')
                    ->searchable(),
                TextColumn::make('check_out_location_accuracy')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('check_out_location_captured_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('work_hours')
                    ->numeric()
                    ->sortable(),
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
