<?php

namespace App\Filament\Resources\AudienceSegments;

use App\Filament\Resources\AudienceSegments\Pages\CreateAudienceSegment;
use App\Filament\Resources\AudienceSegments\Pages\EditAudienceSegment;
use App\Filament\Resources\AudienceSegments\Pages\ListAudienceSegments;
use App\Filament\Resources\AudienceSegments\Schemas\AudienceSegmentForm;
use App\Filament\Resources\AudienceSegments\Tables\AudienceSegmentsTable;
use App\Models\AudienceSegment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AudienceSegmentResource extends Resource
{
    protected static ?string $model = AudienceSegment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AudienceSegmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AudienceSegmentsTable::configure($table);
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
            'index' => ListAudienceSegments::route('/'),
            'create' => CreateAudienceSegment::route('/create'),
            'edit' => EditAudienceSegment::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
