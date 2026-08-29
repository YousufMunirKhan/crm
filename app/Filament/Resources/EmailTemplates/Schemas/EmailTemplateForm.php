<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('category')
                    ->required()
                    ->default('general'),
                TextInput::make('subject')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('content')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('variables')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('is_prebuilt')
                    ->required(),
                FileUpload::make('preview_image')
                    ->image(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }
}
