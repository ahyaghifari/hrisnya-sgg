<?php

namespace App\Repositories\Coaching;

use App\Models\Coaching\Coaching;
use App\Models\Coaching\CoachingProof;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
        $data = Coaching::with('proofs')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();

        if (! $data) {
            throw new ModelNotFoundException();
        }

        return $data;
    }

    public function addProof(string $coachingId, string $path, string $status)
    {
        return CoachingProof::create([
            'coaching_id' => $coachingId,
            'file_path' => $path,
            'status' => $status,
        ]);
    }

    public function updateStatus(string $id, string $status)
    {
        return Coaching::where('id', $id)->update(['status' => $status]);
    }
}
