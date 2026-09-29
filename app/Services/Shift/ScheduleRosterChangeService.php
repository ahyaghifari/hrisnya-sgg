<?php

namespace App\Services\Shift;

use App\Enum\Approval\ApprovalModuleEnum;
use App\Models\Shift\Schedule;
use App\Models\Shift\ScheduleRosterChangeRequest;
use App\Models\Shift\ScheduleShiftAssignment;
use App\Models\User;
use App\Repositories\Approval\ApprovalSnapshotRepository;
use App\Services\Approval\ApprovalCreationService;
use App\Services\Approval\ApprovalFlowService;
use App\Services\Attendance\AccessLogResweepService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kepala Ruangan (punya Update:ShiftScheduleAssignments tapi TIDAK Update:Schedule) tidak boleh
 * langsung menulis roster - proposal ditulis SEBAGAI DRAFT di baris schedule_shift_assignments
 * sendiri (kolom draft_request_id/draft_shift_type_id/draft_off_day, hidup BERDAMPINGAN dengan
 * kolom approved yang TIDAK PERNAH disentuh sampai disetujui - instruksi user 2026-09-28).
 * ScheduleShiftRosterTab (grid asli) yang jadi tempat atasan meninjau & Setuju/Tolak, bukan modal
 * terpisah - lihat ScheduleShiftRosterTab::getCanReviewRosterChangeProperty()/approveRosterChange()/
 * rejectRosterChange(). Approver default = alur diatur admin lewat /admin/approval-flows,
 * instruksi user: approval diisi default saja, tidak perlu pengaju pilih approver.
 *
 * 1 jadwal cuma boleh punya 1 pengajuan `in_review` aktif - Simpan berikutnya (karu terus
 * mengedit selama masih ditinjau) NAMBAH/UBAH sel draft di pengajuan yang SAMA, bukan bikin
 * pengajuan baru. Sel yang diedit balik ke nilai approved-nya sendiri otomatis lepas dari draft
 * (bukan ditolak, cuma dianggap tidak jadi diajukan) - kalau itu sel draft TERAKHIR, seluruh
 * pengajuan ikut otomatis dibatalkan (tidak ada lagi yang perlu ditinjau).
 */
class ScheduleRosterChangeService
{
    public function __construct(
        protected ApprovalCreationService $approvalCreationService,
        protected ApprovalSnapshotRepository $approvalSnapshotRepository,
        protected RosterBatchWriter $writer,
        protected AccessLogResweepService $resweepService,
    ) {}

    /** @param array<int, array{user_id:string, date:string, before:?string, value:?string}> $pending */
    public function submitForApproval(Schedule $schedule, string $period, array $pending, User $user): ScheduleRosterChangeRequest
    {
        return DB::transaction(function () use ($schedule, $period, $pending, $user) {
            $request = ScheduleRosterChangeRequest::where('schedule_id', $schedule->id)
                ->where('status', 'in_review')
                ->first();

            if (! $request) {
                $masterFlow = $this->approvalSnapshotRepository->resolveApprovalFlowForUser(
                    moduleType: ApprovalModuleEnum::SCHEDULE_ROSTER_CHANGE->value,
                    companyId: $user->company_id,
                    userId: $user->id,
                );

                if (! $masterFlow) {
                    throw new \RuntimeException(
                        'Tidak ada alur persetujuan aktif untuk perubahan jadwal shift di perusahaan ini. '
                        . 'Minta admin membuat alur persetujuan default untuk modul ini terlebih dahulu.'
                    );
                }

                $request = ScheduleRosterChangeRequest::create([
                    'company_id' => $schedule->company_id,
                    'schedule_id' => $schedule->id,
                    'period' => $period,
                    'requested_by' => $user->id,
                    'status' => 'in_review',
                ]);

                $this->approvalCreationService->createSnapshot(
                    moduleType: ApprovalModuleEnum::SCHEDULE_ROSTER_CHANGE->value,
                    moduleId: $request->id,
                    flow: $masterFlow,
                    createdBy: $user->id,
                    companyId: $user->company_id,
                    userId: $user->id,
                );
            }

            $this->applyCellsToDraft($schedule, $request, $pending);

            // Semua sel sudah balik ke kondisi approved (revert) - tidak ada lagi yang perlu
            // ditinjau, pengajuan otomatis batal.
            if (! $request->draftAssignments()->exists()) {
                app(ApprovalFlowService::class)->advanceStep(
                    $request->approvalSnapshot()->first(),
                    $user->id,
                    'Semua perubahan dikembalikan ke kondisi semula - pengajuan otomatis dibatalkan.',
                    'cancelled',
                );
            }

            return $request->fresh();
        });
    }

    /** @param array<int, array{user_id:string, date:string, before:?string, value:?string}> $pending */
    private function applyCellsToDraft(Schedule $schedule, ScheduleRosterChangeRequest $request, array $pending): void
    {
        $userIds = array_unique(array_column($pending, 'user_id'));
        $dates = array_unique(array_column($pending, 'date'));

        $existing = $this->writer->shiftAssignmentQuery($schedule)
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [min($dates), max($dates)])
            ->get()
            ->keyBy(fn ($row) => $row->user_id . '|' . $row->date->toDateString());

        $newRows = [];

        foreach ($pending as $cell) {
            $key = $cell['user_id'] . '|' . $cell['date'];
            $row = $existing->get($key);

            $approvedValue = $row ? ($row->off_day ? 'off' : $row->shift_type_id) : null;
            $proposedValue = $cell['value'];

            if ($proposedValue === $approvedValue) {
                if ($row && $row->draft_request_id) {
                    $this->clearDraft($row);
                }

                continue;
            }

            if ($row) {
                $row->update([
                    'draft_request_id' => $request->id,
                    'draft_shift_type_id' => $proposedValue && $proposedValue !== 'off' ? $proposedValue : null,
                    'draft_off_day' => $proposedValue === 'off',
                ]);

                continue;
            }

            if ($proposedValue === null) {
                continue;
            }

            // Baris draft-only baru - insert MENTAH (bukan new Model()->save()) supaya kolom
            // `date` kesimpan persis 'Y-m-d' tanpa jam (cast 'date' Eloquent nulis 'Y-m-d H:i:s'
            // kalau lewat save() biasa - beda dari upsert() RosterBatchWriter yang sudah aman,
            // baris draft-only ini butuh jalur sama biar whereBetween('date', ...) di lookup
            // berikutnya tetap ketemu, bukan malah bikin baris "ganda" tiap Simpan berikutnya).
            $newRows[] = [
                'id' => (string) Str::ulid(),
                'company_id' => $schedule->company_id,
                'user_id' => $cell['user_id'],
                'date' => $cell['date'],
                'shift_type_id' => null,
                'off_day' => false,
                'draft_request_id' => $request->id,
                'draft_shift_type_id' => $proposedValue !== 'off' ? $proposedValue : null,
                'draft_off_day' => $proposedValue === 'off',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($newRows) {
            ScheduleShiftAssignment::insert($newRows);
        }
    }

    /** Buang draft_* dari 1 baris - hapus baris totalnya kalau baris itu memang cuma ada buat
     * nampung draft (tidak pernah punya nilai approved), konsisten sama semantik "sel kosong =
     * tidak ada baris" di RosterBatchWriter. */
    private function clearDraft(ScheduleShiftAssignment $row): void
    {
        if (! $row->shift_type_id && ! $row->off_day) {
            $row->delete();

            return;
        }

        $row->update([
            'draft_request_id' => null,
            'draft_shift_type_id' => null,
            'draft_off_day' => false,
        ]);
    }

    /** Dipanggil ApprovalFlowService::advanceStep() saat step terakhir disetujui - actorId
     * dicatat sebagai changed_by penulis roster tetap pengaju (bukan approver), sel-sel ini
     * memang atas nama Kepala Ruangan, approver cuma menyetujui. Reuse RosterBatchWriter yang
     * sama persis dipakai simpan langsung - draft diperlakukan seolah pengaju baru saja Simpan
     * dengan Update:Schedule. */
    public function executeApproved(ScheduleRosterChangeRequest $request): void
    {
        $draftRows = $request->draftAssignments()->get();

        if ($draftRows->isEmpty()) {
            return;
        }

        $pending = $draftRows->map(fn ($row) => [
            'user_id' => $row->user_id,
            'date' => $row->date->toDateString(),
            'before' => $row->off_day ? 'off' : $row->shift_type_id,
            'value' => $row->draft_off_day ? 'off' : $row->draft_shift_type_id,
        ])->all();

        $this->writer->write($request->schedule, $pending, $request->requested_by);

        // Baris yang MASIH ADA sesudah writer (upsert, bukan delete) - upsert-nya cuma nyentuh
        // kolom approved, draft_*-nya masih nempel, bersihkan manual di sini.
        ScheduleShiftAssignment::where('draft_request_id', $request->id)->update([
            'draft_request_id' => null,
            'draft_shift_type_id' => null,
            'draft_off_day' => false,
        ]);

        $this->resweepPastDates($pending);
    }

    /**
     * Selama pengajuan menunggu, ScheduleResolver cuma baca kolom APPROVED (belum kesentuh) -
     * scan karyawan yang keburu terjadi hari itu ikut resolusi jadwal LAMA (atau off_day kalau
     * sel-nya beneran baru), jatuh ke bucket yang tidak pernah diproses SyncAccessLogsAttendance
     * (lihat AccessLogScheduleReader::groupAndBucket) - silent, tidak ada attendance_records
     * ataupun notifikasi. Begitu disetujui di sini, baca ulang TARGETED per (user, tanggal) yang
     * baru disetujui supaya scan yang tadi jatuh ke bucket itu langsung kebaca ulang dengan
     * jadwal yang benar, tidak nunggu jadwal sweep berkala berikutnya (instruksi user 2026-09-29).
     * Tanggal MASA DEPAN dilewati - belum mungkin ada access_logs buat tanggal yang belum terjadi.
     * Satu (user, tanggal) gagal resweep tidak boleh menggagalkan approval-nya sendiri - approval
     * sudah tuntas ditulis di atas, resweep di sini murni percepatan, bukan bagian transaksi itu.
     *
     * @param array<int, array{user_id:string, date:string, before:?string, value:?string}> $pending
     */
    private function resweepPastDates(array $pending): void
    {
        $today = now()->toDateString();

        $userIdsByDate = collect($pending)
            ->filter(fn ($cell) => $cell['date'] <= $today)
            ->groupBy('date')
            ->map(fn ($cells) => collect($cells)->pluck('user_id')->unique());

        if ($userIdsByDate->isEmpty()) {
            return;
        }

        $nipByUserId = User::whereIn('id', $userIdsByDate->flatten()->unique())->pluck('nip', 'id');

        foreach ($userIdsByDate as $date => $userIds) {
            foreach ($userIds as $userId) {
                $nip = $nipByUserId->get($userId);

                if (! $nip) {
                    continue;
                }

                try {
                    $this->resweepService->resweepUserDate($nip, $date);
                } catch (\Throwable) {
                    // Diam - lihat docblock: resweep gagal tidak boleh menggagalkan approval.
                    // Jaring pengaman attendance:sweep-missed-scans tetap menangkapnya belakangan.
                }
            }
        }
    }

    /** Buang SEMUA draft di bawah pengajuan ini tanpa menulis apa pun ke kolom approved - dipakai
     * saat ditolak maupun dibatalkan (baik otomatis maupun manual). */
    public function discardDrafts(ScheduleRosterChangeRequest $request): void
    {
        foreach ($request->draftAssignments()->get() as $row) {
            $this->clearDraft($row);
        }
    }
}
