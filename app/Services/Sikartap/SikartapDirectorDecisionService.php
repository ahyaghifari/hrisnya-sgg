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

    /**
     * HRD mengajukan pengajuan yang sudah eligible ke Direktur, sekalian set tanggal TMT
     * Karyawan Tetap (kapan pelantikan efektif). $tmtDate boleh null (kompat: alur lama tanpa
     * input tanggal — approve nanti pakai hari-approve). Boleh dipanggil ulang selama masih
     * `diajukan` untuk mengoreksi tanggal.
     */
    public function submitForDecision(Sikartap $sikartap, ?string $tmtDate = null): void
    {
        if (! $sikartap->isEligibleForDirectorDecision()) {
            throw new \RuntimeException('Pengajuan belum memenuhi syarat untuk diajukan ke Direktur.');
        }

        if (! in_array($sikartap->status_keputusan_direktur, ['pending', 'diajukan'], true)) {
            throw new \RuntimeException('Pengajuan ini sudah diputuskan.');
        }

        $directors = $this->directorsFor($sikartap);

        if ($directors->isEmpty()) {
            throw new \RuntimeException('Jabatan Direktur untuk perusahaan ini belum diatur / belum ada pemegangnya. Atur di Pengaturan Sikartap.');
        }

        $wasPending = $sikartap->status_keputusan_direktur === 'pending';

        $sikartap->update([
            'status_keputusan_direktur' => 'diajukan',
            'tmt_tetap' => $tmtDate ? \Carbon\Carbon::parse($tmtDate)->toDateString() : $sikartap->tmt_tetap,
            'keputusan_direktur_diajukan_at' => $sikartap->keputusan_direktur_diajukan_at ?? now(),
            'keputusan_direktur_diajukan_by' => $sikartap->keputusan_direktur_diajukan_by ?? Auth::id(),
        ]);

        if ($wasPending) {
            foreach ($directors as $director) {
                $director->notify(new SikartapKeputusanDirekturDiajukan($sikartap->id, $sikartap->user?->name ?? 'Karyawan'));
            }
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

        $tmt = $sikartap->tmt_tetap ? \Carbon\Carbon::parse($sikartap->tmt_tetap) : \Carbon\Carbon::today();

        $sikartap->update([
            'status_keputusan_direktur' => $decision,
            'keputusan_direktur_by' => $actor->id,
            'keputusan_direktur_at' => now(),
            'keputusan_direktur_note' => trim($note) ?: null,
            'tmt_tetap' => $decision === 'approved' ? $tmt->toDateString() : $sikartap->tmt_tetap,
        ]);

        // Efek "resmi Tetap" langsung kalau TMT sudah tiba; kalau masih di depan, ditunda ke
        // command harian `sikartap:apply-due-tetap`.
        if ($decision === 'approved' && $tmt->lte(\Carbon\Carbon::today())) {
            $this->markUserPermanent($sikartap, $tmt->toDateString());
            $sikartap->update(['keputusan_direktur_applied_at' => now()]);
        }

        $sikartap->user?->notify(new SikartapKeputusanDirekturFinal($sikartap->id, lulus: $decision === 'approved'));
    }

    /**
     * Terapkan pengangkatan Tetap untuk pengajuan yang sudah `approved` & tanggal TMT-nya
     * tiba tapi belum diterapkan (di-defer). Dipanggil command harian `sikartap:apply-due-tetap`.
     *
     * @return array{applied:int, failed:int}
     */
    public function applyDueTetap(): array
    {
        $due = Sikartap::query()
            ->where('status_keputusan_direktur', 'approved')
            ->whereNull('keputusan_direktur_applied_at')
            ->whereNotNull('tmt_tetap')
            ->whereDate('tmt_tetap', '<=', now())
            ->with('user.profile')
            ->get();

        $applied = 0;
        $failed = 0;

        foreach ($due as $sikartap) {
            try {
                $this->markUserPermanent($sikartap, \Carbon\Carbon::parse($sikartap->tmt_tetap)->toDateString());
                $sikartap->update(['keputusan_direktur_applied_at' => now()]);
                $applied++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return ['applied' => $applied, 'failed' => $failed];
    }

    /**
     * Keputusan Direktur final "approved" = karyawan resmi diangkat jadi Tetap per tanggal
     * $tmtDate. Stempel user_profiles.tmt_tetap + flip employee_type_id ke tipe kategori Tetap
     * company itu KALAU cuma ada satu — kalau >1 (atau nol), diserahkan ke HR manual, tmt_tetap
     * tetap tercatat.
     * (Dipindah dari ApprovalFlowService::markUserPermanent() saat Sikartap lepas Approval Engine.)
     */
    private function markUserPermanent(Sikartap $sikartap, string $tmtDate): void
    {
        $sikartap->loadMissing('user.profile');
        $profile = $sikartap->user?->profile;

        if (! $profile) {
            return;
        }

        $updates = ['tmt_tetap' => $tmtDate];

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
