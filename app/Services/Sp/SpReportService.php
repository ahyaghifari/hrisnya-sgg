<?php

namespace App\Services\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportDecision;
use App\Enum\Sp\SpReportStatus;
use App\Models\Sp\SpReport;
use App\Models\User;
use App\Notifications\SpReportUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur Berita Acara baru (instruksi user 2026-10-01):
 *
 *   draft -> menunggu_klarifikasi -> klarifikasi_diajukan -> menunggu_ttd -> selesai
 *                  ^------------------------------------------|  (karyawan ajukan ulang klarifikasi)
 *
 * Tanda tangan sendiri lewat App\Services\Document\DocumentSigningService (generik); service ini
 * hanya mengurus transisi status, siapa boleh apa, dan penutupan otomatis begitu 3 pihak selesai
 * TTD. Pembuatan SP dari BA yang selesai ada di tahap berikutnya (keputusan LANJUT_SP baru
 * disimpan di sini).
 */
class SpReportService
{
    /** HC membuat BA - dasar klarifikasi & kejadian WAJIB sejak awal. */
    public function create(User $creator, array $data): SpReport
    {
        $this->assertCanManage($creator, $data['company_id'] ?? $creator->company_id);

        foreach (['user_id', 'knows_by', 'kejadian', 'dasar_klarifikasi'] as $field) {
            $this->requireFilled($data[$field] ?? null, $field);
        }

        return SpReport::create([
            'user_id' => $data['user_id'],
            'company_id' => $data['company_id'] ?? $creator->company_id,
            'created_by' => $creator->id,
            'knows_by' => $data['knows_by'],
            'nomor' => $data['nomor'] ?? null,
            'kejadian' => $data['kejadian'],
            'dasar_klarifikasi' => $data['dasar_klarifikasi'],
            'status' => SpReportStatus::DRAFT,
        ]);
    }

    /** Edit bagian HC sebelum klarifikasi diminta. */
    public function updateDraft(SpReport $report, User $actor, array $data): SpReport
    {
        $this->assertManage($report, $actor);
        $this->assertStatus($report, SpReportStatus::DRAFT);

        $report->update(array_intersect_key($data, array_flip(['nomor', 'kejadian', 'dasar_klarifikasi', 'knows_by'])));

        return $report;
    }

    public function requestClarification(SpReport $report, User $actor): SpReport
    {
        $this->assertManage($report, $actor);
        $this->assertStatus($report, SpReportStatus::DRAFT);
        $this->requireFilled($report->kejadian, 'kejadian');
        $this->requireFilled($report->dasar_klarifikasi, 'dasar_klarifikasi');

        $report->update(['status' => SpReportStatus::MENUNGGU_KLARIFIKASI]);

        $this->notifyEmployee($report, 'Klarifikasi Diminta', 'Anda diminta mengisi klarifikasi atas sebuah Berita Acara.');

        return $report;
    }

    /** Karyawan yang bersangkutan mengisi klarifikasinya sendiri (apa adanya) lalu mengajukan. */
    public function submitClarification(SpReport $report, User $actor, string $text): SpReport
    {
        abort_unless($actor->id === $report->user_id, 403, 'Hanya karyawan yang bersangkutan yang dapat mengisi klarifikasi.');
        $this->assertStatus($report, SpReportStatus::MENUNGGU_KLARIFIKASI);

        if ($report->klarifikasi_diisi_hc) {
            throw ValidationException::withMessages(['status' => 'Anda sudah meminta HC yang mengisikan klarifikasi.']);
        }

        $this->storeClarification($report, $text);
        $this->notifyCreator($report, 'Klarifikasi Karyawan Masuk', "{$report->user?->name} sudah mengajukan klarifikasi - silakan parafrase dan analisis.");

        return $report;
    }

    /** Karyawan memilih klarifikasinya diisikan langsung oleh HC (bukan ditulis sendiri). */
    public function delegateClarificationToHc(SpReport $report, User $actor): SpReport
    {
        abort_unless($actor->id === $report->user_id, 403, 'Hanya karyawan yang bersangkutan yang dapat memilih ini.');
        $this->assertStatus($report, SpReportStatus::MENUNGGU_KLARIFIKASI);

        $report->update(['klarifikasi_diisi_hc' => true]);
        $this->notifyCreator($report, 'Klarifikasi Diminta Diisikan HC', "{$report->user?->name} meminta HC yang mengisikan klarifikasinya.");

        return $report;
    }

    /** HC mengisi klarifikasi atas nama karyawan - hanya setelah karyawan memilih itu. */
    public function fillClarificationByHc(SpReport $report, User $actor, string $text): SpReport
    {
        $this->assertManage($report, $actor);
        $this->assertStatus($report, SpReportStatus::MENUNGGU_KLARIFIKASI);

        if (! $report->klarifikasi_diisi_hc) {
            throw ValidationException::withMessages(['status' => 'Karyawan belum meminta HC yang mengisikan klarifikasi.']);
        }

        $this->storeClarification($report, $text);

        return $report;
    }

    private function storeClarification(SpReport $report, string $text): void
    {
        $this->requireFilled($text, 'klarifikasi');

        $report->update([
            'subject_clarification' => $text,
            'klarifikasi_diajukan_at' => now(),
            'status' => SpReportStatus::KLARIFIKASI_DIAJUKAN,
        ]);
    }

    /** HC menerima klarifikasi: tulis parafrase + analisis, kesimpulan, rencana tindak lanjut. */
    public function saveAnalysis(SpReport $report, User $actor, array $data): SpReport
    {
        $this->assertManage($report, $actor);
        $this->assertStatus($report, SpReportStatus::KLARIFIKASI_DIAJUKAN);

        $report->update(array_intersect_key($data, array_flip(['klarifikasi_parafrase', 'analysis', 'kesimpulan', 'followup_plan'])));

        return $report;
    }

    /** Isi sudah lengkap -> dibekukan untuk ditandatangani (karyawan lebih dulu). */
    public function sendForSigning(SpReport $report, User $actor): SpReport
    {
        $this->assertManage($report, $actor);
        $this->assertStatus($report, SpReportStatus::KLARIFIKASI_DIAJUKAN);

        foreach (['klarifikasi_parafrase', 'analysis', 'kesimpulan', 'followup_plan'] as $field) {
            $this->requireFilled($report->{$field}, $field);
        }

        DB::transaction(function () use ($report, $actor) {
            $report->update(['status' => SpReportStatus::MENUNGGU_TTD]);
            $report->issueDocument("Berita Acara — {$report->user?->name}", $actor);
        });

        $this->notifyEmployee($report, 'Berita Acara Menunggu Tanda Tangan Anda', 'Baca isi Berita Acara, lalu setujui dan tanda tangani, atau ajukan ulang klarifikasi bila parafrase tidak sesuai maksud Anda.');

        return $report;
    }

    /**
     * Karyawan keberatan dengan parafrase HC -> kembali ke klarifikasi. Hanya sebelum karyawan
     * menandatangani (menandatangani = menerima isi BA). Versi sebelumnya disimpan di riwayat.
     */
    public function requestReclarification(SpReport $report, User $actor, string $reason): SpReport
    {
        abort_unless($actor->id === $report->user_id, 403, 'Hanya karyawan yang bersangkutan yang dapat mengajukan ulang klarifikasi.');
        $this->assertStatus($report, SpReportStatus::MENUNGGU_TTD);
        $this->requireFilled($reason, 'alasan');

        if ($report->document?->signatures()->exists()) {
            throw ValidationException::withMessages(['status' => 'Berita Acara sudah ditandatangani, tidak bisa diajukan ulang.']);
        }

        $report->update([
            'riwayat_klarifikasi' => [
                ...($report->riwayat_klarifikasi ?? []),
                [
                    'klarifikasi' => $report->subject_clarification,
                    'parafrase' => $report->klarifikasi_parafrase,
                    'alasan_ajukan_ulang' => $reason,
                    'at' => now()->toDateTimeString(),
                ],
            ],
            'klarifikasi_parafrase' => null,
            'klarifikasi_diisi_hc' => false,
            'status' => SpReportStatus::MENUNGGU_KLARIFIKASI,
        ]);

        $this->notifyCreator($report, 'Karyawan Mengajukan Ulang Klarifikasi', "{$report->user?->name} keberatan dengan parafrase: {$reason}");

        return $report;
    }

    /**
     * Dipanggil setelah tiap tanda tangan. Karyawan menandatangani -> pembuat & yang mengetahui
     * baru bisa menandatangani (diberi tahu); 3 pihak lengkap -> BA selesai & terkunci, pembuat
     * diberi tahu untuk menetapkan keputusan.
     */
    public function afterSigned(SpReport $report, string $role): SpReport
    {
        $report->unsetRelation('document');

        if ($report->status === SpReportStatus::MENUNGGU_TTD && $report->document?->isFullySigned()) {
            $report->update(['status' => SpReportStatus::SELESAI]);
            $this->notifyCreator($report, 'Berita Acara Selesai', "Berita Acara {$report->user?->name} sudah ditandatangani semua pihak - tetapkan keputusan layak SP atau tidak.");

            return $report;
        }

        if ($role === 'employee') {
            foreach ([$report->createdBy, $report->knowsBy] as $signer) {
                $signer?->notify(new SpReportUpdated(
                    'Berita Acara Menunggu Tanda Tangan Anda',
                    "{$report->user?->name} sudah menyetujui Berita Acara - giliran Anda menandatangani.",
                    documentSignId: $report->document?->id,
                ));
            }
        }

        return $report;
    }

    /** Keputusan HC setelah BA selesai: layak SP atau tidak. */
    public function decide(SpReport $report, User $actor, SpReportDecision $decision): SpReport
    {
        $this->assertManage($report, $actor);
        $this->assertStatus($report, SpReportStatus::SELESAI);

        if ($report->keputusan !== null) {
            throw ValidationException::withMessages(['keputusan' => 'Keputusan sudah ditetapkan.']);
        }

        $report->update(['keputusan' => $decision]);

        return $report;
    }

    /**
     * Buat SP dari BA yang sudah SELESAI dengan keputusan LANJUT_SP (instruksi user 2026-10-01).
     * Level SP & isi surat ditentukan di sini, bukan di BA. BA ditautkan lewat sp_id - 1 BA
     * menghasilkan paling banyak 1 SP (dijaga lewat lock baris + unique sp_id).
     *
     * @param  array{nomor:string, sp_level:string, start_date:string, knows_by:string, items:array<int,string>, coaching_id?:?string}  $data
     */
    public function createSp(SpReport $report, User $actor, array $data): \App\Models\Sp\EmployeeSp
    {
        $this->assertManage($report, $actor);

        return DB::transaction(function () use ($report, $actor, $data) {
            $report = SpReport::lockForUpdate()->findOrFail($report->id);

            if ($report->status !== SpReportStatus::SELESAI || $report->keputusan !== SpReportDecision::LANJUT_SP || $report->sp_id !== null) {
                throw ValidationException::withMessages(['report_id' => 'Berita Acara ini tidak dapat dijadikan SP (belum selesai, tidak diputuskan lanjut SP, atau sudah punya SP).']);
            }

            $level = \App\Enum\Sp\SpLevel::from($data['sp_level']);
            $coachingId = $data['coaching_id'] ?? null;

            $sp = \App\Models\Sp\EmployeeSp::create([
                'nomor' => $data['nomor'],
                'user_id' => $report->user_id,
                'company_id' => $report->company_id,
                'sp_level' => $level->value,
                'source_type' => $coachingId ? \App\Enum\Sp\SpSourceType::COACHING->value : \App\Enum\Sp\SpSourceType::MANUAL->value,
                'source_id' => $coachingId,
                'start_date' => $data['start_date'],
                'end_date' => app(SpService::class)->resolveEndDate($level, $report->company_id, $data['start_date']),
                'created_by' => $actor->id,
                'knows_by' => $data['knows_by'],
            ]);

            foreach (array_values($data['items']) as $index => $text) {
                $sp->items()->create(['text' => $text, 'sort_order' => $index]);
            }

            $report->update(['sp_id' => $sp->id]);

            return $sp;
        });
    }

    private function notifyEmployee(SpReport $report, string $title, string $body): void
    {
        $report->user?->notify(new SpReportUpdated($title, $body, route('sp.saya.ba', $report)));
    }

    private function notifyCreator(SpReport $report, string $title, string $body): void
    {
        $report->createdBy?->notify(new SpReportUpdated($title, $body, route('ba.show', $report)));
    }

    private function assertCanManage(User $actor, ?string $companyId): void
    {
        abort_unless(
            $actor->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $actor->can('Create:EmployeeSp')
                && $actor->company_id === $companyId,
            403,
            'Anda tidak berwenang mengelola Berita Acara.'
        );
    }

    private function assertManage(SpReport $report, User $actor): void
    {
        abort_unless(
            $actor->id === $report->created_by
                || ($actor->can(CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $actor->company_id === $report->company_id),
            403,
            'Anda tidak berwenang mengelola Berita Acara ini.'
        );
    }

    private function assertStatus(SpReport $report, SpReportStatus $expected): void
    {
        if ($report->status !== $expected) {
            throw ValidationException::withMessages([
                'status' => "Tindakan ini hanya bisa pada status \"{$expected->getLabel()}\".",
            ]);
        }
    }

    private function requireFilled(mixed $value, string $field): void
    {
        if (! filled($value)) {
            throw ValidationException::withMessages([$field => "Kolom {$field} wajib diisi."]);
        }
    }
}
