<?php

namespace App\Services\Coaching;

use App\Enum\CoachingStatus;
use App\Enum\CounselingStatus;
use App\Models\Coaching\Coaching;
use App\Models\Coaching\CoachingProof;
use App\Models\Counseling\Counseling;
use App\Repositories\Coaching\CoachingRepository;
use App\Repositories\Counseling\CounselingRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CoachingService
{
    public function __construct(
        protected CoachingRepository $coachingRepository
    ) {}

    public function getList(): Collection
    {
        $user = Auth::user();
        return $this->coachingRepository->getByUser($user->id);
    }

    public function getDetail(string $id): Coaching
    {
        $user = Auth::user();
        return $this->coachingRepository->getDetail($id, $user->id);
    }

    public function update(string $coachingId, $request)
    {
        return DB::transaction(function () use ($coachingId, $request) {
            $coaching = $this->coachingRepository->getDetail($coachingId, Auth::id());

            if ($request->hasFile('proofs')) {
                foreach ($request->file('proofs') as $file) {
                    $path = $file->store('coachings/proofs', 'public');

                    $this->coachingRepository->addProof(
                        $coaching->id,
                        $path,
                        CoachingStatus::SUBMITTED->value
                    );
                }
            }

            $this->coachingRepository->updateStatus(
                $coachingId,
                CoachingStatus::SUBMITTED->value
            );

            return $this->coachingRepository->getDetail($coachingId, Auth::id());
        });
    }
}
