<?php

namespace App\Filament\Resources\Salaries;

use App\Filament\Resources\Salaries\Pages\CreateSalary;
use App\Filament\Resources\Salaries\Pages\EditSalary;
use App\Filament\Resources\Salaries\Pages\ListSalaries;
use App\Filament\Resources\Salaries\Schemas\SalaryForm;
use App\Filament\Resources\Salaries\Tables\SalariesTable;
use App\Modules\HR\Models\Salary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalaryResource extends Resource
{
    protected static ?string $model = Salary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'People & money';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'month';

    public static function form(Schema $schema): Schema
    {
        return SalaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalariesTable::configure($table);
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
            'index' => ListSalaries::route('/'),
            'create' => CreateSalary::route('/create'),
            'edit' => EditSalary::route('/{record}/edit'),
        ];
    }
}
