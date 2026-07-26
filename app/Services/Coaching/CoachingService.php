<?php

namespace App\Services\Coaching;

use App\Enum\CoachingStatus;
use App\Enum\CounselingStatus;
use App\Models\Coaching\Coaching;
use App\Models\Coaching\CoachingProof;
use App\Models\Counseling\Counseling;
use App\Models\User;
use App\Notifications\CoachingAssigned;
use App\Notifications\CoachingProofSubmitted;
use App\Notifications\CoachingReviewDecided;
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
        $coaching = DB::transaction(function () use ($coachingId, $request) {
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

        User::find($coaching->coach_by)?->notify(new CoachingProofSubmitted($coaching));

        return $coaching;
    }

    /**
     * Padanan update() di atas, tapi buat portal Livewire ("Coaching Saya") — beda dari
     * Illuminate\Http\Request (mobile), Livewire\WithFileUploads kasih array TemporaryUploadedFile
     * langsung (tidak lewat request()->file()), jadi butuh method terpisah alih-alih maksa
     * signature yang sama. Efeknya identik: simpan tiap file sebagai CoachingProof(SUBMITTED),
     * lalu tandai Coaching-nya SUBMITTED juga.
     */
    public function uploadProofs(Coaching $coaching, array $files): Coaching
    {
        $coaching = DB::transaction(function () use ($coaching, $files) {
            foreach ($files as $file) {
                $path = $file->store('coachings/proofs', 'public');

                $this->coachingRepository->addProof(
                    $coaching->id,
                    $path,
                    CoachingStatus::SUBMITTED->value
                );
            }

            $this->coachingRepository->updateStatus($coaching->id, CoachingStatus::SUBMITTED->value);

            return $coaching->refresh();
        });

        User::find($coaching->coach_by)?->notify(new CoachingProofSubmitted($coaching));

        return $coaching;
    }

    // --- Sisi HC (portal /coaching) — beda dari method di atas (sisi karyawan yang dibina,
    // scoped user_id via CoachingRepository). Method di bawah ini dipakai HC bikin coaching
    // untuk siapa saja & approve/reject bukti, jadi tidak lewat repository yang di-scope
    // user_id. Alur & efek identik dengan admin Filament (CreateCoaching/EditCoaching).

    public function create(array $data): Coaching
    {
        $data['status'] = CoachingStatus::IN_PROGRESS->value;

        $coaching = Coaching::create($data);

        User::find($coaching->user_id)?->notify(new CoachingAssigned($coaching));

        return $coaching;
    }

    /**
     * Bukti yang sudah diunggah ikut ditandai statusnya (SUBMITTED -> COMPLETED/REJECTED) —
     * dikembalikan lagi (instruksi user 2026-07-26) setelah sempat disederhanakan jadi
     * "status Coaching saja" di sesi sebelumnya.
     */
    public function approve(Coaching $coaching): void
    {
        $coaching->update(['status' => CoachingStatus::COMPLETED->value]);

        $coaching->proofs()
            ->where('status', CoachingStatus::SUBMITTED->value)
            ->update(['status' => CoachingStatus::COMPLETED->value]);

        User::find($coaching->user_id)?->notify(new CoachingReviewDecided($coaching, approved: true));
    }

    public function reject(Coaching $coaching): void
    {
        $coaching->update(['status' => CoachingStatus::REJECTED->value]);

        $coaching->proofs()
            ->where('status', CoachingStatus::SUBMITTED->value)
            ->update(['status' => CoachingStatus::REJECTED->value]);

        User::find($coaching->user_id)?->notify(new CoachingReviewDecided($coaching, approved: false));
    }
}
