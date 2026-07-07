<?php

namespace App\Filament\Candidate\Resources\JobApplicants\Pages;

use App\Filament\Candidate\Pages\Dashboard;
use App\Filament\Candidate\Resources\JobApplicants\JobApplicantResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListJobApplicants extends ListRecords
{
    protected static string $resource = JobApplicantResource::class;
    protected static ?string $title = 'Daftar Lamaran Saya';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apply_job')
                ->label('Lamar Pekerjaan')
                ->url(Dashboard::getUrl())
        ];
    }
}
