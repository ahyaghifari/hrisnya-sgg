<?php

namespace App\Filament\Resources\Assessment\UserAssessments\Pages;

use App\Filament\Resources\Assessment\UserAssessments\UserAssessmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserAssessment extends CreateRecord
{
    protected static string $resource = UserAssessmentResource::class;

    protected static ?string $title = 'Buat Penilaian Karyawan';
}
