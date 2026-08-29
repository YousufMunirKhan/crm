<?php

namespace App\Filament\Resources\Salaries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->searchable(),
                TextColumn::make('month')
                    ->searchable(),
                TextColumn::make('base_salary')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('allowances')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('house_allowance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('medical_allowance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('other_allowance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deductions')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tax')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('loan_deduction')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('other_deduction')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('net_salary')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('attendance_days')
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
