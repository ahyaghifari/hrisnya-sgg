<?php

namespace App\Repositories\Coaching;

use App\Models\Coaching\Coaching;
use Illuminate\Database\Eloquent\Collection;

class CoachingRepository
{
    public function getByUser(string $userId): Collection
    {
        return Coaching::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function getDetail(string $id, string $userId): Coaching
    {
        return Coaching::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();
    }
}
