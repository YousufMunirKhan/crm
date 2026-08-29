<?php

namespace App\Filament\Resources\ContactConsents\Pages;

use App\Filament\Resources\ContactConsents\ContactConsentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContactConsent extends EditRecord
{
    protected static string $resource = ContactConsentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
