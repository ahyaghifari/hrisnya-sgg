<?php

namespace App\Services\Sikartap;

use App\Enum\EmployeeTypeCategory;
use App\Models\Master\EmployeeTypes;
use App\Models\Setting;
use App\Models\Sikartap\Sikartap;
use App\Models\User;
use App\Models\User\UserJobPositions;
use App\Notifications\Sikartap\SikartapKeputusanDirekturDiajukan;
use App\Notifications\Sikartap\SikartapKeputusanDirekturFinal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Fase 4 — Keputusan Direktur. LEPAS dari Approval Engine (instruksi user 2026-09-07): tidak
 * ada flow approval berjenjang, cukup 1 jabatan Direktur per perusahaan (Setting
 * `sikartap_direktur_job_position_id`, diatur di Pengaturan Sikartap). Direktur =
 * pemegang aktif jabatan itu.
 *
 * State: pending -> diajukan -> approved | rejected. Kolom keputusan_direktur_* di tabel
 * sikartap menyimpan jejak yang dulu ada di approval_snapshots/approval_histories.
 */
class SikartapDirectorDecisionService
{
    public const SETTING_KEY = 'sikartap_direktur_job_position_id';

    /** @return Collection<int, User> pemegang aktif jabatan Direktur perusahaan pelamar. */
    public function directorsFor(Sikartap $sikartap): Collection
    {
        $jobPositionId = Setting::resolve(self::SETTING_KEY, $sikartap->user?->company_id, default: null);

        if (! $jobPositionId) {
            return collect();
        }

        return UserJobPositions::query()
            ->where('job_position_id', $jobPositionId)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function canDecide(Sikartap $sikartap, User $user): bool
    {
        return $this->directorsFor($sikartap)->contains('id', $user->id);
    }

    /** HRD mengajukan pengajuan yang sudah eligible ke Direktur. */
    public function submitForDecision(Sikartap $sikartap): void
    {
        if (! $sikartap->isEligibleForDirectorDecision()) {
            throw new \RuntimeException('Pengajuan belum memenuhi syarat untuk diajukan ke Direktur.');
        }

        if ($sikartap->status_keputusan_direktur !== 'pending') {
            throw new \RuntimeException('Pengajuan ini sudah diajukan atau sudah diputuskan.');
        }

        $directors = $this->directorsFor($sikartap);

        if ($directors->isEmpty()) {
            throw new \RuntimeException('Jabatan Direktur untuk perusahaan ini belum diatur / belum ada pemegangnya. Atur di Pengaturan Sikartap.');
        }

        $sikartap->update([
            'status_keputusan_direktur' => 'diajukan',
            'keputusan_direktur_diajukan_at' => now(),
            'keputusan_direktur_diajukan_by' => Auth::id(),
        ]);

        foreach ($directors as $director) {
            $director->notify(new SikartapKeputusanDirekturDiajukan($sikartap->id, $sikartap->user?->name ?? 'Karyawan'));
        }
    }

    public function approve(Sikartap $sikartap, User $actor, string $note): void
    {
        $this->decide($sikartap, $actor, 'approved', $note);
    }

    public function reject(Sikartap $sikartap, User $actor, string $note): void
    {
        $this->decide($sikartap, $actor, 'rejected', $note);
    }

    private function decide(Sikartap $sikartap, User $actor, string $decision, string $note): void
    {
        if ($sikartap->status_keputusan_direktur !== 'diajukan') {
            throw new \RuntimeException('Pengajuan ini tidak sedang menunggu keputusan Direktur.');
        }

        if (! $this->canDecide($sikartap, $actor)) {
            throw new \RuntimeException('Anda bukan Direktur yang ditunjuk untuk memutuskan pengajuan ini.');
        }

        if ($decision === 'rejected' && trim($note) === '') {
            throw new \RuntimeException('Catatan penolakan wajib diisi.');
        }

        $sikartap->update([
            'status_keputusan_direktur' => $decision,
            'keputusan_direktur_by' => $actor->id,
            'keputusan_direktur_at' => now(),
            'keputusan_direktur_note' => trim($note) ?: null,
        ]);

        if ($decision === 'approved') {
            $this->markUserPermanent($sikartap);
        }

        $sikartap->user?->notify(new SikartapKeputusanDirekturFinal($sikartap->id, lulus: $decision === 'approved'));
    }

    /**
     * Keputusan Direktur final "approved" = karyawan resmi diangkat jadi Tetap. Stempel
     * user_profiles.tmt_tetap + flip employee_type_id ke tipe kategori Tetap company itu KALAU
     * cuma ada satu — kalau >1 (atau nol), diserahkan ke HR manual, tmt_tetap tetap tercatat.
     * (Dipindah dari ApprovalFlowService::markUserPermanent() saat Sikartap lepas Approval Engine.)
     */
    private function markUserPermanent(Sikartap $sikartap): void
    {
        $sikartap->loadMissing('user.profile');
        $profile = $sikartap->user?->profile;

        if (! $profile) {
            return;
        }

        $updates = ['tmt_tetap' => now()->toDateString()];

        $tetapTypeIds = EmployeeTypes::query()
            ->where('company_id', $sikartap->user->company_id)
            ->where('category', EmployeeTypeCategory::TETAP->value)
            ->pluck('id');

        if ($tetapTypeIds->count() === 1) {
            $updates['employee_type_id'] = $tetapTypeIds->first();
        }

        $profile->update($updates);
    }
}
