<?php

namespace App\Filament\Resources\ManPower\JobOpenings\Pages;

use App\Filament\Resources\ManPower\JobOpenings\JobOpeningResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobOpening extends CreateRecord
{
    protected static string $resource = JobOpeningResource::class;
}
