<?php

namespace App\Filament\Resources\AttendanceRecords\Pages;

use App\Filament\Resources\AttendanceRecords\AttendanceRecordResource;
use App\Filament\Resources\AttendanceRecords\Schemas\AttendanceRecordCreateForm;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;

class CreateAttendanceRecord extends CreateRecord
{
    protected static string $resource = AttendanceRecordResource::class;

    public function form(Schema $schema): Schema
    {
        return AttendanceRecordCreateForm::configure($schema);
    }
}
