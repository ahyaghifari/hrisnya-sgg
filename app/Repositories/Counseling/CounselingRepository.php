<?php

namespace App\Repositories\Counseling;

use App\Models\Counseling\Counseling;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CounselingRepository
{
    public function getByUser(string $userId): Collection
    {
        return Counseling::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function findDetail(string $id, string $userId): Counseling
    {
        $data = Counseling::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (! $data) {
            throw new ModelNotFoundException();
        }

        return $data;
    }

    public function create(array $data): Counseling
    {
        return Counseling::create($data);
    }
}
