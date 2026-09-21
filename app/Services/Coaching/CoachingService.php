<?php

namespace App\Services\Coaching;

use App\Models\Coaching\Coaching;
use App\Models\User;
use App\Notifications\CoachingAssigned;
use App\Repositories\Coaching\CoachingRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CoachingService
{
    public function __construct(
        protected CoachingRepository $coachingRepository
    ) {}

    public function getList(): Collection
    {
        return $this->coachingRepository->getByUser(Auth::id());
    }

    public function getDetail(string $id): Coaching
    {
        return $this->coachingRepository->getDetail($id, Auth::id());
    }

    /**
     * Coaching satu arah (instruksi user 2026-09-22): diberikan = selesai, tidak ada bukti
     * pelaksanaan/review dari karyawan yang dibina. Dipakai portal HC (CreateCoachingPage).
     */
    public function create(array $data): Coaching
    {
        $coaching = Coaching::create($data + ['created_by' => Auth::id()]);

        User::find($coaching->user_id)?->notify(new CoachingAssigned($coaching));

        return $coaching;
    }
}
