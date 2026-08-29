<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('business_name')
                    ->searchable(),
                TextColumn::make('owner_name')
                    ->searchable(),
                TextColumn::make('contact_person_2_name')
                    ->searchable(),
                TextColumn::make('contact_person_2_phone')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('whatsapp_number')
                    ->searchable(),
                TextColumn::make('sms_number')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('email_secondary')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable(),
                TextColumn::make('postcode')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('vat_number')
                    ->searchable(),
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('anydesk_rustdesk')
                    ->searchable(),
                TextColumn::make('epos_type')
                    ->searchable(),
                TextColumn::make('lic_days')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('birthday')
                    ->date()
                    ->sortable(),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude')
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
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
