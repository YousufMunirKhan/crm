<?php

namespace App\Filament\Resources\EmployeeTargets;

use App\Filament\Resources\EmployeeTargets\Pages\CreateEmployeeTarget;
use App\Filament\Resources\EmployeeTargets\Pages\EditEmployeeTarget;
use App\Filament\Resources\EmployeeTargets\Pages\ListEmployeeTargets;
use App\Filament\Resources\EmployeeTargets\Schemas\EmployeeTargetForm;
use App\Filament\Resources\EmployeeTargets\Tables\EmployeeTargetsTable;
use App\Modules\HR\Models\EmployeeTarget;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmployeeTargetResource extends Resource
{
    protected static ?string $model = EmployeeTarget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|\UnitEnum|null $navigationGroup = 'People & money';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return EmployeeTargetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmployeeTargetsTable::configure($table);
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
            'index' => ListEmployeeTargets::route('/'),
            'create' => CreateEmployeeTarget::route('/create'),
            'edit' => EditEmployeeTarget::route('/{record}/edit'),
        ];
    }
}
