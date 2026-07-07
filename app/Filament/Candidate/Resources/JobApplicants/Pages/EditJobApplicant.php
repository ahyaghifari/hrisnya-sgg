<?php

namespace App\Filament\Candidate\Resources\JobApplicants\Pages;

use App\Filament\Candidate\Resources\JobApplicants\JobApplicantResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJobApplicant extends EditRecord
{
    protected static string $resource = JobApplicantResource::class;

    protected static ?string $title = 'Detail Lamaran Pekerjaan';

    public function getFormActions(): array
    {
        return [];
    }
}
