<?php

namespace App\Filament\Resources\ContactConsents;

use App\Filament\Resources\ContactConsents\Pages\CreateContactConsent;
use App\Filament\Resources\ContactConsents\Pages\EditContactConsent;
use App\Filament\Resources\ContactConsents\Pages\ListContactConsents;
use App\Filament\Resources\ContactConsents\Schemas\ContactConsentForm;
use App\Filament\Resources\ContactConsents\Tables\ContactConsentsTable;
use App\Models\ContactConsent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ContactConsentResource extends Resource
{
    protected static ?string $model = ContactConsent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'identifier';

    public static function form(Schema $schema): Schema
    {
        return ContactConsentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactConsentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactConsents::route('/'),
            'create' => CreateContactConsent::route('/create'),
            'edit' => EditContactConsent::route('/{record}/edit'),
        ];
    }
}
