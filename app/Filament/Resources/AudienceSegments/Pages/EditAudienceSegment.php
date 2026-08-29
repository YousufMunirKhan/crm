<?php

namespace App\Filament\Resources\AudienceSegments\Pages;

use App\Filament\Resources\AudienceSegments\AudienceSegmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAudienceSegment extends EditRecord
{
    protected static string $resource = AudienceSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
