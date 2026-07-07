<?php

namespace App\Services\JobOpening;

use App\Repositories\JobOpening\JobOpeningRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class JobOpeningService
{
    public function __construct(
        protected JobOpeningRepository $jobOpeningRepositories,
    ) {}

    public function getList(int $perPage = 10): LengthAwarePaginator
    {
        return $this->jobOpeningRepositories->getAll($perPage);
    }

    public function getDetail(string $id)
    {
        return $this->jobOpeningRepositories->getDetail($id);
    }
}
