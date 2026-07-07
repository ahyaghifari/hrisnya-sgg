<?php

namespace App\Filament\Resources\ManPower\JobOpenings\Pages;

use App\Filament\Resources\ManPower\JobOpenings\JobOpeningResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobOpenings extends ListRecords
{
    protected static string $resource = JobOpeningResource::class;
    
    protected static ?string $title = 'Manajemen Lowongan Kerja';
}
