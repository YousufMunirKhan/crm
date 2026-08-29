<?php

namespace App\Filament\Resources\EmployeeTargets\Pages;

use App\Filament\Resources\EmployeeTargets\EmployeeTargetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeTargets extends ListRecords
{
    protected static string $resource = EmployeeTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
