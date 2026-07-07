<?php

namespace App\Services\Counseling;

use App\Enum\CounselingStatus;
use App\Models\Counseling\Counseling;
use App\Repositories\Counseling\CounselingRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CounselingService
{
    public function __construct(
        protected CounselingRepository $counselingRepository
    ) {}

    public function getList(): Collection
    {
        $user = Auth::user();
        return $this->counselingRepository->getByUser($user->id);
    }

    public function getDetail(string $id): Counseling
    {
        $user = Auth::user();
        return $this->counselingRepository->findDetail($id, $user->id);
    }

    public function create($request)
    {
        return DB::transaction(function () use ($request) {
            $user = Auth::user();

            return $this->counselingRepository->create([
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'issue' => $request->issue,
                'status' => CounselingStatus::PENDING->value,
            ]);
        });
    }
}
