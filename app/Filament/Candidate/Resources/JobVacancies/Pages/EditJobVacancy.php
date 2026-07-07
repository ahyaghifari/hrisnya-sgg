<?php

namespace App\Filament\Candidate\Resources\JobVacancies\Pages;

use App\Filament\Candidate\Resources\JobApplicants\JobApplicantResource;
use App\Filament\Candidate\Resources\JobVacancies\JobVacancyResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJobVacancy extends EditRecord
{
    protected static string $resource = JobVacancyResource::class;

    protected static ?string $title = 'Detail Lowongan Pekerjaan';

    protected static ?string $breadcrumb = 'Detail Lowongan Pekerjaan';

    public function getFormActions(): array
    {
        return [];
    }
}
