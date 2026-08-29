<?php

namespace App\Filament\Resources\AudienceSegments\Pages;

use App\Filament\Resources\AudienceSegments\AudienceSegmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAudienceSegments extends ListRecords
{
    protected static string $resource = AudienceSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
