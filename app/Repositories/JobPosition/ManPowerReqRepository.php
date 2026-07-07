<?php

namespace App\Repositories\JobPosition;

use App\Models\Job\JobOpenings;
use App\Models\Job\ManpowerRequests;

class ManPowerReqRepository
{
    public function create(array $data): ManpowerRequests
    {
        return ManpowerRequests::create($data);
    }

    public function update(ManpowerRequests $manpowerRequest, array $data): bool
    {
        return $manpowerRequest->update($data);
    }

    public function createJobOpening(array $data): JobOpenings
    {
        return JobOpenings::create($data);
    }
}
