<?php

namespace App\Filament\Resources\Attendances\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                DatePicker::make('date')
                    ->required(),
                DateTimePicker::make('check_in_at'),
                TextInput::make('check_in_photo_path'),
                TextInput::make('check_in_latitude')
                    ->numeric(),
                TextInput::make('check_in_longitude')
                    ->numeric(),
                TextInput::make('check_in_location_name'),
                TextInput::make('check_in_location_accuracy')
                    ->numeric(),
                DateTimePicker::make('check_in_location_captured_at'),
                DateTimePicker::make('check_out_at'),
                TextInput::make('check_out_photo_path'),
                TextInput::make('check_out_latitude')
                    ->numeric(),
                TextInput::make('check_out_longitude')
                    ->numeric(),
                TextInput::make('check_out_location_name'),
                TextInput::make('check_out_location_accuracy')
                    ->numeric(),
                DateTimePicker::make('check_out_location_captured_at'),
                TextInput::make('work_hours')
                    ->required()
                    ->numeric()
                    ->default(0.0),
            ]);
    }
}
