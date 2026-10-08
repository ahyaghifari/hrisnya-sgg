<?php

namespace App\Services\Sp;

use App\Enum\CustomPermissionEnum;
use App\Enum\Sp\SpReportDecision;
use App\Enum\Sp\SpReportStatus;
use App\Models\Sp\SpReport;
use App\Models\User;
use App\Notifications\SpReportUpdated;
use App\Services\SikartapReviewerResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur Berita Acara baru (instruksi user 2026-10-01; disederhanakan 2026-10-07: semua isian HC -
 * dasar, kejadian, klarifikasi, analisis, kesimpulan, tindak lanjut - diisi sekaligus di tahap
 * draft, FIX langsung ke tanda tangan):
 *
 *   draft -> menunggu_ttd -> selesai
 *     ^---------|  (karyawan ajukan ulang klarifikasi sebelum ada yang TTD)
 *
 * Tanda tangan sendiri lewat App\Services\Document\DocumentSigningService (generik); service ini
 * hanya mengurus transisi status, siapa boleh apa, dan penutupan otomatis begitu 3 pihak selesai
 * TTD. Pembuatan SP dari BA yang selesai ada di tahap berikutnya (keputusan LANJUT_SP baru
 * disimpan di sini).
 */
class SpReportService
{
    /** Isian HC selain dasar/kejadian - boleh kosong selama draft, wajib saat FIX. */
    private const HC_CONTENT = ['subject_clarification', 'analysis', 'kesimpulan', 'followup_plan'];

    /** HC membuat BA - dasar klarifikasi & kejadian WAJIB sejak awal. */
    public function create(User $creator, array $data): SpReport
    {
        $this->assertCanManage($creator, $data['company_id'] ?? $creator->company_id);

        foreach (['user_id', 'knows_by', 'kejadian'] as $field) {
            $this->requireFilled($data[$field] ?? null, $field);
        }
        $this->requireFilled(array_filter($data['dasar_items'] ?? [], fn ($item) => is_array($item) ? filled($item['reason_id'] ?? null) || filled($item['note'] ?? null) : filled(trim($item))), 'dasar_klarifikasi');

        return DB::transaction(function () use ($creator, $data) {
            $report = SpReport::create([
                'user_id' => $data['user_id'],
                'company_id' => $data['company_id'] ?? $creator->company_id,
                'created_by' => $creator->id,
                'knows_by' => $data['knows_by'],
                'nomor' => $data['nomor'] ?? null,
                'kejadian' => $data['kejadian'],
                'status' => SpReportStatus::DRAFT,
            ] + array_intersect_key($data, array_flip(self::HC_CONTENT)));
            $report->syncDasarItems($data['dasar_items']);

            return $report;
        });
    }

    /** Edit seluruh isian HC (dasar, kejadian, klarifikasi, analisis, ...) selama masih draft. */
    public function updateDraft(SpReport $report, User $actor, array $data): SpReport
    {
        $this->assertManage($report, $actor);
        $this->assertStatus($report, SpReportStatus::DRAFT);

        $report->update(array_intersect_key($data, array_flip(['nomor', 'kejadian', 'knows_by', ...self::HC_CONTENT])));

        if (array_key_exists('dasar_items', $data)) {
            $report->syncDasarItems($data['dasar_items']);
        }

        return $report;
    }

    /** Draft lengkap -> dibekukan untuk ditandatangani (pembuat lebih dulu, karyawan terakhir). */
    public function sendForSigning(SpReport $report, User $actor): SpReport
    {
        $this->assertManage($report, $actor);
        $this->assertStatus($report, SpReportStatus::DRAFT);

        $this->requireFilled($report->dasarItems()->exists(), 'dasar_klarifikasi');
        // Trix kosong tetap mengirim markup (<div><br></div>) - cek isi teksnya.
        foreach (['kejadian', ...self::HC_CONTENT] as $field) {
            $this->requireFilled(trim(strip_tags((string) $report->{$field})), $field);
        }

        // Atasan langsung ikut TTD, kecuali dia sudah pembuat/mengetahui (satu orang tidak TTD dua peran).
        $supervisor = app(SikartapReviewerResolver::class)->directSupervisorOf($report->user);
        $supervisorId = $supervisor && ! in_array($supervisor->id, [$report->created_by, $report->knows_by, $report->user_id], true) ? $supervisor->id : null;

        DB::transaction(function () use ($report, $actor, $supervisorId) {
            $report->update([
                'status' => SpReportStatus::MENUNGGU_TTD,
                'supervisor_id' => $supervisorId,
                'klarifikasi_diisi_hc' => true,
                'klarifikasi_diajukan_at' => now(),
            ]);
            $report->issueDocument("Berita Acara — {$report->user?->name}", $actor);
        });

        $this->notifyEmployee($report, 'Berita Acara Telah Disusun', 'Berita Acara Anda telah disusun dan sedang ditandatangani atasan langsung, HC, dan yang mengetahui. Anda menandatangani terakhir.');

        // Penanda tangan pertama (atasan langsung kalau ada, selain itu HC sendiri tidak perlu dikabari).
        $report->unsetRelation('document');
        $first = array_key_first($report->expectedSignerRoles());
        if ($first === 'supervisor') {
            $report->supervisor?->notify(new SpReportUpdated(
                'Berita Acara Menunggu Tanda Tangan Anda',
                "Berita Acara {$report->user?->name} dibuat atas nama Anda sebagai atasan langsung - giliran Anda menandatangani lebih dulu.",
                documentSignId: $report->document?->id,
            ));
        }

        return $report;
    }

    /**
     * Karyawan keberatan dengan isi klarifikasi -> kembali ke klarifikasi. Hanya sebelum karyawan
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

        $report->document?->unfix($actor); // isi akan berubah -> buka kunci, dikunci lagi di sendForSigning

        $report->update([
            'riwayat_klarifikasi' => [
                ...($report->riwayat_klarifikasi ?? []),
                [
                    'klarifikasi' => $report->subject_clarification,
                    'diisi_hc' => (bool) $report->klarifikasi_diisi_hc,
                    'alasan_ajukan_ulang' => $reason,
                    'at' => now()->toDateTimeString(),
                ],
            ],
            'klarifikasi_diisi_hc' => false,
            'status' => SpReportStatus::DRAFT,
        ]);

        $this->notifyCreator($report, 'Karyawan Mengajukan Ulang Klarifikasi', "{$report->user?->name} keberatan dengan isi klarifikasi: {$reason}");

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

        // Urutan = expectedSignerRoles(). Hanya pihak giliran berikutnya yang dikabari.
        $order = array_keys($report->expectedSignerRoles());
        $nextRole = $order[array_search($role, $order, true) + 1] ?? null;
        $users = $report->signerUsers();

        ($nextRole ? $users[$nextRole] : null)?->notify(new SpReportUpdated(
            'Berita Acara Menunggu Tanda Tangan Anda',
            "{$users[$role]?->name} sudah menandatangani Berita Acara - giliran Anda menandatangani.",
            documentSignId: $report->document?->id,
        ));

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
     * @param  array{nomor:string, sp_level:string, start_date:string, knows_by:string, items:array<int,string|array{reason_id?:?string, repeat_sp_id?:?string, note?:?string}>, coaching_id?:?string}  $data
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

            foreach (array_values($data['items']) as $index => $item) {
                $sp->items()->create(\App\Models\Sp\SpReason::composeItem($item) + ['sort_order' => $index]);
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
        // Pembuat BA selalu dikabari (tidak bisa dimatikan lewat pengaturan notifikasi).
        $report->createdBy?->notify(new SpReportUpdated($title, $body, route('ba.show', $report), always: true));
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
