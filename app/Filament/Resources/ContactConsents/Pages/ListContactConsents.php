<?php

namespace App\Filament\Resources\ContactConsents\Pages;

use App\Filament\Resources\ContactConsents\ContactConsentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContactConsents extends ListRecords
{
    protected static string $resource = ContactConsentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
