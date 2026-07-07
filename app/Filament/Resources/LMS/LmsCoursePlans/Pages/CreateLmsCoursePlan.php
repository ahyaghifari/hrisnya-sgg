<?php

namespace App\Filament\Resources\LMS\LmsCoursePlans\Pages;

use App\Filament\Resources\LMS\LmsCoursePlans\LmsCoursePlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLmsCoursePlan extends CreateRecord
{
    protected static string $resource = LmsCoursePlanResource::class;
    protected static ?string $title = 'Rencana Pelatihan Baru';
}
