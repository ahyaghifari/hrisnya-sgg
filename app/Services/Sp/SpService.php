<?php

namespace App\Services\Sp;

use App\Enum\MeritPoint\MeritPointEvent;
use App\Enum\Pembinaan\PembinaanSettingEnum;
use App\Services\MeritPoint\MeritPointService;
use App\Enum\Sp\SpLevel;
use App\Enum\Sp\SpRecommendationDecision;
use App\Models\Setting;
use App\Models\Sp\EmployeeSp;
use App\Models\Sp\SpRecommendation;
use App\Models\User;
use App\Models\UserHrBalance;
use App\Notifications\SpAwaitingSignature;
use App\Notifications\SpIssued;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Logic bersama penerbitan SP (planning/2026-09-23-sp-dan-merit-point.md §2), dipakai baik jalur
 * manual (EmployeeSpResource) maupun approve sp_recommendations (§2.4) - supaya perhitungan
 * end_date & refresh cache tidak dobel ditulis di 2 tempat.
 *
 * PENYESUAIAN (instruksi user 2026-09-25): employee_sps direstrukturisasi (nomor, sp_items,
 * sp_reports - lihat migration 2026_09_25_100000_restructure_employee_sps_table). Jalur approve
 * recommendation di bawah ini SENGAJA cuma bikin SP minimal (nomor NULL, 1 sp_item dari
 * reason_note lama, TANPA sp_reports) - recommendation tidak punya data sekaya form Buat SP
 * manual, dan jalur ini sendiri sedang nonaktif (recommendationCreationEnabled() false). HR bisa
 * lengkapi nomor/items/report manual lewat EmployeeSpResource kalau jalur ini dipakai lagi nanti.
 */
class SpService
{
    /**
     * Kill-switch GLOBAL sementara (instruksi user 2026-09-23) - tombol "Rekomendasikan SP" di
     * CoachingDetailPage & checkbox di CreateCoachingPage dinonaktifkan dulu (permission
     * SISTEM_PEMBINAAN_KARYAWAN belum di-assign ke role selain Super Admin). Approve/reject
     * rekomendasi yang SUDAH ADA (SpRecommendationsPage, SpRecommendationResource Filament)
     * TIDAK ikut dimatikan - cuma jalur BIKIN rekomendasi baru dari Coaching yang ditutup.
     * Balikin true kalau sudah siap rilis.
     */
    public static function recommendationCreationEnabled(): bool
    {
        return false;
    }

    /** SP1/SP2/SP3 semua dari PembinaanSettingEnum, company-scoped (instruksi user 2026-09-25:
     * SP3 sekarang juga punya batas hari, default 180 - sebelumnya SP3 tidak pernah expired). */
    public function resolveEndDate(SpLevel $level, ?string $companyId, string $startDate): ?string
    {
        $settingCase = match ($level) {
            SpLevel::SP1 => PembinaanSettingEnum::SP1_DURASI_HARI,
            SpLevel::SP2 => PembinaanSettingEnum::SP2_DURASI_HARI,
            SpLevel::SP3 => PembinaanSettingEnum::SP3_DURASI_HARI,
        };

        $days = (int) Setting::resolve($settingCase->value, $companyId, (int) $settingCase->default());

        return Carbon::parse($startDate)->addDays($days)->toDateString();
    }

    /** Dipanggil saat SP benar-benar TERBIT (bukan cuma disimpan draft) - refresh cache (§3.5) +
     *  notif karyawan. Portal (CreateSpPage) baru memanggil ini belakangan lewat tombol "Tanda
     *  Tangan & Terbitkan" di SpDetailPage (instruksi user 2026-09-29); jalur approve
     *  recommendation & Filament EmployeeSpResource masih memanggilnya langsung setelah create
     *  (tidak ada langkah draft di jalur itu). */
    public function afterIssued(EmployeeSp $employeeSp): void
    {
        $this->refreshBalance($employeeSp->user_id);

        // SP alur baru: karyawan baru dikabari saat gilirannya TTD (setelah "Mengetahui"), bukan saat terbit.
        if (! $employeeSp->requiresEmployeeSignature()) {
            User::find($employeeSp->user_id)?->notify(new SpIssued($employeeSp));
        }

        app(MeritPointService::class)->applyEvent(
            MeritPointEvent::from($employeeSp->sp_level->value),
            $employeeSp->user_id,
            $employeeSp->company_id,
            'employee_sp',
            $employeeSp->id,
            'Penerbitan ' . $employeeSp->sp_level->name,
        );

        $this->signCreatedByAndNotifyKnowsBy($employeeSp);
    }

    /**
     * TTE Fase 1, level "TTE Tidak Tersertifikasi" (docs/HRIS_TTE_NonTersertifikasi_QR_Verification.md
     * §4, Pasal 59 ayat 3 butir 6 PP 71/2019 - persetujuan tiap penanda tangan harus eksplisit dari
     * ORANG itu sendiri, bukan diwakilkan). created_by ditandatangani DI SINI - konsen created_by
     * sendiri sudah diminta lewat konfirmasi password di SpDetailPage::issue() SEBELUM method ini
     * dipanggil (tombol "Tanda Tangan & Terbitkan", instruksi user 2026-09-29), jadi bukan "auto
     * tanpa consent".
     *
     * knows_by SENGAJA TIDAK ditandatangani di sini (instruksi user 2026-09-28: "yang mengetahui
     * itu klik TTD setujui, isi password ulang, baru di-TTD") - dia cuma DITUNJUK di form, tanda
     * tangannya sendiri baru tercatat lewat SignDocumentsPage setelah dia login & konfirmasi
     * password sendiri (lihat App\Services\Document\DocumentSigningService::sign() - generik,
     * bukan method khusus SP lagi). Berita Acara (kalau ada) di-sign TERPISAH sebagai dokumennya
     * sendiri (dokumen beda, TTD dibedakan) - dipinjam penanda tangan yang sama karena sp_reports
     * tidak punya kolom penanda tangan sendiri.
     */
    private function signCreatedByAndNotifyKnowsBy(EmployeeSp $employeeSp): void
    {
        $employeeSp->loadMissing(['createdBy', 'knowsBy', 'items', 'report', 'user']);

        $createdBy = $employeeSp->createdBy;
        $knowsBy = $employeeSp->knowsBy;

        if (! $createdBy || ! $knowsBy) {
            return;
        }

        // sign() sekarang langsung membangkitkan kode/QR verifikasi-nya sendiri per TTD
        // (HasDocumentSignatures::sign(), instruksi user 2026-09-29) - tidak perlu panggilan
        // ensureVerificationCode() terpisah lagi.
        $employeeSp->issueDocument("Surat Peringatan {$employeeSp->sp_level->getLabel()} — {$employeeSp->user?->name}", $createdBy);
        $employeeSp->sign($createdBy, 'created_by');

        // BA alur baru sudah ditandatangani sendiri sebelum SP ada - hanya BA lama yang ikut paket SP.
        if ($employeeSp->report?->isLegacy()) {
            $employeeSp->report->issueDocument("Berita Acara — {$employeeSp->user?->name}", $createdBy);
            $employeeSp->report->sign($createdBy, 'created_by');
        }

        $knowsBy->notify(new SpAwaitingSignature($employeeSp));
    }

    // Tanda tangan knows_by sendiri (setelah dia login & konfirmasi password) TIDAK ditangani di
    // sini lagi - lihat App\Services\Document\DocumentSigningService::sign(), generik lintas
    // jenis dokumen (instruksi user 2026-09-28: "kenapa dibangun erat dengan SP, kan bisa modal,
    // nanti ada juga Surat Tugas dan yang lainnya").

    /**
     * Revisi SP yang sudah terbit walau sebagian sudah menandatangani (hanya sebelum semua TTD selesai):
     * tanda tangan dibatalkan (jejak di event dokumen) dan dokumen dibuka untuk diedit. Penerbitan efek
     * (merit point, notifikasi karyawan) TIDAK diulang; pembuat menandatangani ulang lewat reissue().
     * BA lama yang ikut paket SP ikut dibuka.
     */
    public function revise(EmployeeSp $employeeSp, User $actor, string $reason): void
    {
        abort_unless(
            $actor->can(\App\Enum\CustomPermissionEnum::SISTEM_PEMBINAAN_KARYAWAN->value) && $actor->company_id === $employeeSp->company_id,
            403,
            'Anda tidak berwenang merevisi Surat Peringatan ini.'
        );
        abort_if($employeeSp->isCancelled() || ! $employeeSp->isIssued(), 409, 'Hanya SP yang sudah terbit dan belum dibatalkan yang bisa direvisi.');

        if (! filled($reason)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['alasan' => 'Alasan revisi wajib diisi.']);
        }

        DB::transaction(function () use ($employeeSp, $actor, $reason) {
            $employeeSp->document->revise($actor, $reason);

            if ($employeeSp->report?->isLegacy()) {
                $employeeSp->report->document?->revise($actor, $reason);
            }

            $employeeSp->update(['revised_at' => now()]);
        });

        collect([$employeeSp->knowsBy, $employeeSp->user])->filter()->reject(fn ($user) => $user->id === $actor->id)
            ->each(fn ($user) => $user->notify(new \App\Notifications\SpReportUpdated(
                'Surat Peringatan Direvisi',
                "Surat Peringatan {$employeeSp->user?->name} direvisi HC: {$reason}. Tanda tangan sebelumnya dibatalkan; Anda dikabari saat giliran menandatangani.",
                $user->id === $employeeSp->user_id ? route('sp.saya.show', $employeeSp) : route('sp.show', $employeeSp),
                always: true,
            )));
    }

    /** Terbitkan ulang SP yang sudah direvisi: kunci dokumen lagi, pembuat tanda tangan, Mengetahui dikabari. */
    public function reissue(EmployeeSp $employeeSp): void
    {
        $this->refreshBalance($employeeSp->user_id); // tanggal berlaku bisa berubah saat revisi
        $this->signCreatedByAndNotifyKnowsBy($employeeSp);
    }

    /** Batal SP (instruksi user 2026-09-25) - soft-cancel, bukan hard delete: record tetap ada
     * buat riwayat/audit, cuma ditandai cancelled_at + alasan + siapa yang membatalkan.
     * scopeActive() otomatis exclude SP yang sudah dibatalkan, jadi cache active_sp_level perlu
     * di-refresh ulang sama seperti afterIssued() (level aktif user bisa berubah/hilang). */
    public function cancel(EmployeeSp $employeeSp, string $reason, string $cancelledById): void
    {
        $employeeSp->update([
            'cancelled_at' => now(),
            'cancelled_reason' => $reason,
            'cancelled_by' => $cancelledById,
        ]);

        $actor = \App\Models\User::find($cancelledById);
        foreach ([$employeeSp->document, $employeeSp->report?->document] as $document) {
            $document?->logEvent('document_revoked', $actor, ['reason' => $reason]);
        }

        $this->refreshBalance($employeeSp->user_id);

        app(MeritPointService::class)->revokeEvent($employeeSp->user_id, 'employee_sp', $employeeSp->id);
    }

    /**
     * Setujui rekomendasi (§2.4) - HR boleh ubah level & tanggal mulai dari saran awal
     * recommendation, reason/catatan tetap dari recommendation (tidak diubah saat review).
     */
    public function approveRecommendation(
        SpRecommendation $recommendation,
        SpLevel $level,
        string $startDate,
        string $reviewerId
    ): EmployeeSp {
        return DB::transaction(function () use ($recommendation, $level, $startDate, $reviewerId) {
            $employeeSp = EmployeeSp::create([
                'user_id' => $recommendation->user_id,
                'company_id' => $recommendation->company_id,
                'sp_level' => $level->value,
                'source_type' => $recommendation->source_type->value,
                'source_id' => $recommendation->source_id,
                'start_date' => $startDate,
                'end_date' => $this->resolveEndDate($level, $recommendation->company_id, $startDate),
                'created_by' => $reviewerId,
                'knows_by' => $reviewerId,
            ]);

            $employeeSp->items()->create([
                'text' => $recommendation->reason_note,
                'sort_order' => 0,
            ]);

            $recommendation->update([
                'decision' => SpRecommendationDecision::APPROVED->value,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'employee_sp_id' => $employeeSp->id,
            ]);

            $this->afterIssued($employeeSp);

            return $employeeSp;
        });
    }

    /** Tolak - TIDAK ada employee_sps yang dibuat (§2.4). */
    public function rejectRecommendation(SpRecommendation $recommendation, string $reviewerId): void
    {
        $recommendation->update([
            'decision' => SpRecommendationDecision::REJECTED->value,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Cache tampilan widget Karir (§3.5) - active_sp_level diambil dari SP aktif TERTINGGI.
     * merit_point sengaja TIDAK disentuh di sini (dikelola terpisah oleh alur merit_point_transactions).
     */
    public function refreshBalance(string $userId): UserHrBalance
    {
        $rank = fn (EmployeeSp $sp) => match ($sp->sp_level) {
            SpLevel::SP3 => 3,
            SpLevel::SP2 => 2,
            SpLevel::SP1 => 1,
        };

        $activeSp = EmployeeSp::active()
            ->where('user_id', $userId)
            ->get()
            ->sortByDesc($rank)
            ->first();

        return UserHrBalance::updateOrCreate(
            ['user_id' => $userId],
            [
                'active_sp_level' => $activeSp?->sp_level,
                'active_sp_id' => $activeSp?->id,
            ]
        );
    }

    /**
     * Job harian (§3.5) - SP kadaluarsa murni karena tanggal lewat, TANPA event/write apa pun,
     * jadi cache bisa basi kalau tidak disapu. Cuma query user yang active_sp_id terisi (murah),
     * BUKAN semua user - lihat komentar migration user_hr_balances.
     */
    public function refreshExpiredBalances(): int
    {
        $userIds = UserHrBalance::query()->whereNotNull('active_sp_id')->pluck('user_id');

        foreach ($userIds as $userId) {
            $this->refreshBalance($userId);
        }

        return $userIds->count();
    }
}
