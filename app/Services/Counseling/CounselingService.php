<?php

namespace App\Services\Counseling;

use App\Enum\CounselingStatus;
use App\Models\Counseling\Counseling;
use App\Models\User;
use App\Notifications\CounselingReceived;
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
        $counseling = DB::transaction(function () use ($request) {
            $user = Auth::user();

            return $this->counselingRepository->create([
                'user_id' => $user->id,
                'counselor_id' => $request->counselor_id,
                'issue' => $request->issue,
                'status' => CounselingStatus::PENDING->value,
                'is_secret' => $request->is_secret ?? true,
            ]);
        });

        User::find($counseling->counselor_id)?->notify(new CounselingReceived($counseling));

        return $counseling;
    }

    // --- Sisi HC (portal /counseling/kelola) — balas keluhan karyawan. Alur identik dengan
    // admin Filament CounselingForm (isi response+solution), plus tandai selesai (DONE) di sini
    // karena admin tidak punya status transition eksplisit selain edit field biasa.
    public function respond(Counseling $counseling, string $response, string $solution): void
    {
        $counseling->update([
            'response' => $response,
            'solution' => $solution,
            'status' => CounselingStatus::DONE->value,
            'responded_by' => Auth::id(),
            'responded_at' => now(),
        ]);
    }
}
