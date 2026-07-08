<?php

namespace App\Filament\Resources\EmployeeScheduleResource\Pages;

use App\Filament\Resources\EmployeeScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSchedule extends EditRecord
{
    protected static string $resource = EmployeeScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
