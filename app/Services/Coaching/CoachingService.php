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

    // --- Sisi HC (portal /coaching) — beda dari method di atas (sisi karyawan yang dibina,
    // scoped user_id via CoachingRepository). Method di bawah ini dipakai HC bikin coaching
    // untuk siapa saja & approve/reject bukti, jadi tidak lewat repository yang di-scope
    // user_id. Alur & efek identik dengan admin Filament (CreateCoaching/EditCoaching).

    public function create(array $data): Coaching
    {
        $data['status'] = CoachingStatus::IN_PROGRESS->value;

        return Coaching::create($data);
    }

    public function approve(Coaching $coaching): void
    {
        $coaching->update(['status' => CoachingStatus::COMPLETED->value]);

        $coaching->proofs()
            ->where('status', CoachingStatus::SUBMITTED->value)
            ->update(['status' => CoachingStatus::COMPLETED->value]);
    }

    public function reject(Coaching $coaching): void
    {
        $coaching->update(['status' => CoachingStatus::REJECTED->value]);

        $coaching->proofs()
            ->where('status', CoachingStatus::SUBMITTED->value)
            ->update(['status' => CoachingStatus::REJECTED->value]);
    }
}
