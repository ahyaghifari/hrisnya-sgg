<?php

namespace App\Filament\Candidate\Resources\JobVacancies\Pages;

use App\Filament\Candidate\Resources\JobVacancies\JobVacancyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobVacancy extends CreateRecord
{
    protected static string $resource = JobVacancyResource::class;
}
