<?php

namespace App\Filament\Resources\ScheduleShifts\Pages;

use App\Filament\Resources\ScheduleShifts\ScheduleShiftResource;
use App\Filament\Resources\ScheduleShifts\Schemas\ScheduleShiftForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditScheduleShift extends EditRecord
{
    protected static string $resource = ScheduleShiftResource::class;

    public function form(Schema $schema): Schema
    {
        return ScheduleShiftForm::editConfigure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
