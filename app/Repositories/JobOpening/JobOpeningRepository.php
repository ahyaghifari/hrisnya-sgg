<?php

namespace App\Repositories\JobOpening;

use App\Models\Job\JobOpenings;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class JobOpeningRepository
{
    public function getAll(int $perPage = 10): LengthAwarePaginator
    {
        return JobOpenings::query()
            ->latest()
            ->paginate($perPage);
    }

    public function getDetail($id)
    {
        return JobOpenings::find($id);
    }
}
