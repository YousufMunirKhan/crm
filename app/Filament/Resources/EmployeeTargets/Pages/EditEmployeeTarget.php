<?php

namespace App\Filament\Resources\EmployeeTargets\Pages;

use App\Filament\Resources\EmployeeTargets\EmployeeTargetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeTarget extends EditRecord
{
    protected static string $resource = EmployeeTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
