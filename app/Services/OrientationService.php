<?php

namespace App\Services;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Enum\NotificationTypeEnum;
use App\Enum\OrientationDecisionEnum;
use App\Models\OrientationSubmission;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrientationReviewed;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use Illuminate\Support\Facades\DB;

/**
 * Alur penilaian orientasi (self-service). Pengajuan + upload bukti oleh karyawan orientasi →
 * Approval Engine (ORIENTATION_REQUEST). Approver terakhir (atasan langsung) mengisi skor per
 * indikator + memilih keputusan (lulus/perpanjang/tidak_lulus) di step yang sama.
 *
 * Indikator penilaian = Setting `orientation_criteria` per company (bukan hardcode) — HR ubah
 * lewat halaman Indikator Orientasi.
 */
class OrientationService
{
    public const CRITERIA_SETTING_KEY = 'orientation_criteria';

    public function __construct(
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalFlowService $approvalFlowService,
    ) {}

    /**
     * Daftar indikator penilaian untuk 1 company.
     *
     * @return array<int, array{name: string, group_label: ?string, max_score: float}>
     */
    public function criteria(?string $companyId): array
    {
        $raw = Setting::resolve(self::CRITERIA_SETTING_KEY, $companyId, default: null);

        $list = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($list)) {
            return [];
        }

        return collect($list)
            ->map(fn ($row) => [
                'name' => (string) ($row['name'] ?? ''),
                'group_label' => $row['group_label'] ?? null,
                'max_score' => (float) ($row['max_score'] ?? 5),
            ])
            ->filter(fn ($row) => $row['name'] !== '')
            ->values()
            ->all();
    }

    /** Simpan daftar indikator untuk 1 company (dipakai halaman Indikator Orientasi). */
    public function saveCriteria(?string $companyId, array $list): void
    {
        Setting::updateOrCreate(
            ['key' => self::CRITERIA_SETTING_KEY, 'company_id' => $companyId],
            ['value' => json_encode(array_values($list))],
        );
    }

    /** Kirim pengajuan (dari status draft) → buat approval snapshot. */
    public function submit(OrientationSubmission $submission, User $actor): void
    {
        if ($submission->status !== 'draft') {
            throw new \RuntimeException('Pengajuan ini sudah dikirim.');
        }

        if (empty($submission->attachments)) {
            throw new \RuntimeException('Unggah minimal satu bukti kegiatan orientasi dulu.');
        }

        $masterFlow = $this->approvalSnapshotRepository->findMasterApprovalFlowByCompany(
            moduleType: ApprovalModuleEnum::ORIENTATION_REQUEST->value,
            companyId: $submission->company_id,
        );

        if (! $masterFlow) {
            throw new \RuntimeException('Belum ada alur persetujuan untuk Penilaian Orientasi di perusahaan ini. Hubungi HRD.');
        }

        DB::transaction(function () use ($submission, $actor, $masterFlow) {
            $submission->update([
                'status' => 'menunggu_persetujuan',
                'submitted_at' => now(),
            ]);

            $this->approvalCreationService->createSnapshot(
                moduleType: ApprovalModuleEnum::ORIENTATION_REQUEST->value,
                moduleId: $submission->id,
                flow: $masterFlow,
                createdBy: $actor->id,
                companyId: $submission->company_id,
                userId: $actor->id,
            );
        });
    }

    /**
     * Aksi approver TERAKHIR: simpan skor + keputusan, lalu advance (approve) approval snapshot.
     * Efek final (status selesai, extended_until, notifikasi) hanya dieksekusi kalau snapshot
     * benar-benar jadi `approved` — regresi klasik ManPower.
     *
     * @param array<int, array{criterion: string, score: float, comment: ?string}> $scores
     * @param int|null $extensionMonths Wajib diisi kalau $decision = 'perpanjang' - lama perpanjangan orientasi (bulan).
     */
    public function review(OrientationSubmission $submission, string $actorId, array $scores, string $decision, string $note, ?int $extensionMonths = null): void
    {
        $decisionEnum = OrientationDecisionEnum::from($decision);

        if (trim($note) === '') {
            throw new \RuntimeException('Catatan keputusan wajib diisi.');
        }

        if ($decisionEnum === OrientationDecisionEnum::PERPANJANG && (! $extensionMonths || $extensionMonths < 1)) {
            throw new \RuntimeException('Lama perpanjangan orientasi (bulan) wajib diisi.');
        }

        DB::transaction(function () use ($submission, $actorId, $scores, $decisionEnum, $note, $extensionMonths) {
            $this->approvalFlowService->advanceStep($submission->approvalSnapshot, $actorId, $note, 'approved');

            $submission->refresh();
            $isFinal = $submission->approvalSnapshot?->refresh()->status === 'approved';

            $updates = [
                'scores' => array_values($scores),
                'status' => 'dinilai',
            ];

            if ($isFinal) {
                $updates['status'] = 'selesai';
                $updates['decision'] = $decisionEnum->value;
                $updates['decision_note'] = $note;
                $updates['decided_by'] = $actorId;
                $updates['decided_at'] = now();

                if ($decisionEnum === OrientationDecisionEnum::PERPANJANG) {
                    // Perpanjangan: baris user_orientations BARU (menyambung dari periode terakhir),
                    // source='extension'. extended_until = end_date baris baru, buat tampilan cepat.
                    $last = $submission->user?->orientations()->orderByDesc('start_date')->first();
                    $anchor = $last?->end_date && $last->end_date->isFuture()
                        ? $last->end_date->copy()
                        : \Carbon\Carbon::today();

                    $newEnd = $anchor->copy()->addMonths($extensionMonths);

                    \App\Models\User\UserOrientation::create([
                        'user_id' => $submission->user_id,
                        'company_id' => $submission->company_id,
                        'start_date' => $anchor->toDateString(),
                        'end_date' => $newEnd->toDateString(),
                        'duration_months' => $extensionMonths,
                        'source' => 'extension',
                        'source_submission_id' => $submission->id,
                        'previous_orientation_id' => $last?->id,
                    ]);

                    $updates['extended_until'] = $newEnd->toDateString();
                }
            }

            $submission->update($updates);

            if ($isFinal) {
                $submission->user?->notify(new OrientationReviewed($submission));
            }
        });
    }

    public function reject(OrientationSubmission $submission, string $actorId, ?string $note = null): void
    {
        DB::transaction(function () use ($submission, $actorId, $note) {
            $this->approvalFlowService->advanceStep($submission->approvalSnapshot, $actorId, $note, 'rejected');

            $submission->update([
                'status' => 'selesai',
                'decision' => OrientationDecisionEnum::TIDAK_LULUS->value,
                'decision_note' => $note,
                'decided_by' => $actorId,
                'decided_at' => now(),
            ]);

            $submission->user?->notify(new OrientationReviewed($submission));
        });
    }

    /** Batalkan pengajuan (oleh pengaju sendiri) selama belum ada keputusan. */
    public function cancel(OrientationSubmission $submission, string $actorId): void
    {
        if (in_array($submission->status, ['selesai', 'dibatalkan'], true)) {
            throw new \RuntimeException('Pengajuan ini sudah tidak bisa dibatalkan.');
        }

        DB::transaction(function () use ($submission, $actorId) {
            if ($submission->approvalSnapshot) {
                $this->approvalFlowService->advanceStep($submission->approvalSnapshot, $actorId, null, 'cancelled');
            }

            $submission->update(['status' => 'dibatalkan']);
        });
    }
}
